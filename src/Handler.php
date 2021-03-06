<?php

namespace DrupalComposer\DrupalL10n;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\CommandEvent;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;

/**
 * Handler that do the actual stuff.
 */
class Handler {

  const PRE_DRUPAL_L10N_CMD = 'pre-drupal-l10n-cmd';
  const POST_DRUPAL_L10N_CMD = 'post-drupal-l10n-cmd';
  const DRUPAL_L10N_PACKAGE_TYPES = [
    'drupal-core',
    'drupal-module',
    'drupal-theme',
    'drupal-profile',
  ];

  /**
   * The composer object.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * The input output interface.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * The Drupal core package.
   *
   * @var \Composer\Package\PackageInterface
   */
  protected $drupalCorePackage;

  /**
   * The packages installed during a command execution.
   *
   * @var \Composer\Package\PackageInterface[]
   */
  protected $packagesInstalledDuringCommand;

  /**
   * A boolean indicating if progress should be displayed.
   *
   * @var bool
   */
  protected $progress;

  /**
   * Handler constructor.
   *
   * @param \Composer\Composer $composer
   *   The composer object.
   * @param \Composer\IO\IOInterface $io
   *   The input output interface.
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->packagesInstalledDuringCommand = [];
    $this->progress = TRUE;
  }

  /**
   * Get the command options.
   *
   * @param \Composer\Plugin\CommandEvent $event
   *   The command event.
   */
  public function onCmdBeginsEvent(CommandEvent $event) {
    if ($event->getInput()->hasOption('no-progress')) {
      $this->progress = !($event->getInput()->getOption('no-progress'));
    }
    else {
      $this->progress = TRUE;
    }
  }

  /**
   * Prepare a list of installed packages.
   *
   * The localization for this packages will be downloaded after an install or
   * update command.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   A package event.
   */
  public function onPostPackageEvent(PackageEvent $event) {
    $package = $this->getOperationPackage($event->getOperation());
    if ($package) {
      $this->packagesInstalledDuringCommand[] = $package;
    }
  }

  /**
   * Post install command event to execute the download localization.
   *
   * @param \Composer\Script\Event $event
   *   A composer event.
   */
  public function onPostCmdEvent(Event $event) {
    // Download localization for installed packages.
    if (!empty($this->packagesInstalledDuringCommand)) {
      $this->downloadLocalization($event->isDevMode(), $this->packagesInstalledDuringCommand);
    }
  }

  /**
   * Downloads drupal localization files for the current process.
   *
   * @param bool $dev
   *   TRUE if dev packages are installed. FALSE otherwise.
   * @param \Composer\Package\PackageInterface[] $packages
   *   A list of Packages to download the localization. If empty, the
   *   localization for all Drupal packages detected in the installation will be
   *   downloaded.
   */
  public function downloadLocalization($dev = TRUE, array $packages = []) {
    $drupal_core_package = $this->getDrupalCorePackage();
    // Ensure drupal core package is present.
    if (is_null($drupal_core_package)) {
      $this->io->writeError('The drupal core package could not be found. Abort.');
      return;
    }

    $webroot = realpath($this->getWebRoot());

    // Prepare a list of Drupal project to download the translations.
    $drupal_projects = [];
    if (empty($packages)) {
      $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
    }
    foreach ($packages as $package) {
      // Filter by the type of package.
      if (in_array($package->getType(), $this::DRUPAL_L10N_PACKAGE_TYPES)) {
        // Include development project or not.
        if (!$package->isDev() || ($package->isDev() == $dev)) {
          // We require the package to have a specific version.
          $package_name = $package->getName();
          $drupal_package_version = $this->extractPackageVersion($package_name, $package->getPrettyVersion());
          if (!empty($drupal_package_version)) {
            $drupal_projects[$package_name] = $drupal_package_version;
          }
        }
      }
    }

    // Call any pre-l10n scripts that may be defined.
    $dispatcher = new EventDispatcher($this->composer, $this->io);
    $dispatcher->dispatch(self::PRE_DRUPAL_L10N_CMD);

    // Get the Drupal core version.
    $core_version = $this->getDrupalCoreVersion($drupal_core_package);

    // Collect options.
    $options = $this->getOptions();

    $httpDownloader = new HttpDownloader($this->io, $this->composer->getConfig());

    $fetcher = new FileFetcher($this->io, $httpDownloader, $options, $core_version, $this->progress);
    $fetcher->fetch($drupal_projects, $webroot . '/' . $options['destination']);

    // Call post-l10n scripts.
    $dispatcher->dispatch(self::POST_DRUPAL_L10N_CMD);
  }

  /**
   * Get the path to the 'vendor' directory.
   *
   * @return string
   *   The path to the vendor directory.
   */
  public function getVendorPath() {
    $config = $this->composer->getConfig();
    $filesystem = new Filesystem();
    $filesystem->ensureDirectoryExists($config->get('vendor-dir'));
    $vendorPath = $filesystem->normalizePath(realpath($config->get('vendor-dir')));

    return $vendorPath;
  }

  /**
   * Look up the Drupal core package object.
   *
   * @return \Composer\Package\PackageInterface
   *   The drupal Core package instance.
   */
  public function getDrupalCorePackage() {
    if (!isset($this->drupalCorePackage)) {
      // Drupal 8.
      $this->drupalCorePackage = $this->getPackage('drupal/core');
      // Drupal 7.
      if (is_null($this->drupalCorePackage)) {
        $this->drupalCorePackage = $this->getPackage('drupal/drupal');
      }
    }
    return $this->drupalCorePackage;
  }

  /**
   * Returns the Drupal core version for the given package.
   *
   * @param \Composer\Package\PackageInterface $drupalCorePackage
   *   The Drupal core package object.
   *
   * @return string
   *   The Drupal core version. Example: 8.3.2.
   */
  protected function getDrupalCoreVersion(PackageInterface $drupalCorePackage) {
    $version = $drupalCorePackage->getPrettyVersion();
    if ($drupalCorePackage->getStability() == 'dev' && substr($version, -4) == '-dev') {
      $version = substr($version, 0, -4);
      return $version;
    }
    return $version;
  }

  /**
   * Retrieve the path to the web root.
   *
   * @return string
   *   The path to the web root.
   */
  public function getWebRoot() {
    $drupalCorePackage = $this->getDrupalCorePackage();
    $installationManager = $this->composer->getInstallationManager();
    $corePath = $installationManager->getInstallPath($drupalCorePackage);
    if ($drupalCorePackage->getName() == 'drupal/drupal') {
      $webroot = $corePath;
    }
    // Webroot is the parent path of the drupal core installation path for
    // Drupal 8.
    else {
      $webroot = dirname($corePath);
    }

    return $webroot;
  }

  /**
   * Helper function to get the package of an operation.
   *
   * @param \Composer\DependencyResolver\Operation\OperationInterface $operation
   *   The current operation object.
   *
   * @return false|\Composer\Package\PackageInterface
   *   Returns the operation package if found, NULL otherwise.
   */
  protected function getOperationPackage(OperationInterface $operation) {
    if ($operation instanceof InstallOperation) {
      $package = $operation->getPackage();
    }
    elseif ($operation instanceof UpdateOperation) {
      $package = $operation->getTargetPackage();
    }
    if (isset($package) && $package instanceof PackageInterface) {
      return $package;
    }
    return FALSE;
  }

  /**
   * Retrieve a package from the current composer process.
   *
   * @param string $name
   *   Name of the package to get from the current composer installation.
   *
   * @return \Composer\Package\PackageInterface
   *   A package object.
   */
  protected function getPackage($name) {
    return $this->composer->getRepositoryManager()->getLocalRepository()->findPackage($name, '*');
  }

  /**
   * Retrieve excludes from optional "extra" configuration.
   *
   * @return array
   *   An array of the options.
   */
  protected function getOptions() {
    $extra = $this->composer->getPackage()->getExtra() + ['drupal-l10n' => []];
    $options = $extra['drupal-l10n'] + [
      'destination' => 'sites/default/files/translations',
      'languages' => [],
    ];
    return $options;
  }

  /**
   * Helper function to extract and convert a package version into a Drupal one.
   *
   * @param string $package_name
   *   The package name.
   * @param string $package_pretty_version
   *   The package version as returned by $package->getPrettyVersion().
   *
   * @return array
   *   Empty array if the package does not have a specific version (opposed to a
   *   specific commit or a dev version). Otherwise, version prepared for semver
   *   or drupal format.
   */
  protected function extractPackageVersion($package_name, $package_pretty_version) {
    preg_match("/^(\d+)\.(\d+)\.(\d+)(-.*)?$/", $package_pretty_version, $parsed_version);
    // Not a specific version.
    if (empty($parsed_version)) {
      return [];
    }

    // Special case for Drupal core.
    if (in_array($package_name, ['drupal/core', 'drupal/drupal'])) {
      return [
        'drupal_format' => $package_pretty_version,
        'semver_format' => $package_pretty_version,
      ];
    }
    else {
      $major_version = $parsed_version[1];
      $minor_version = $parsed_version[2];
      $patch_version = $parsed_version[3];
      $version_status = isset($parsed_version[4]) ? $parsed_version[4] : '';

      return [
        'drupal_format' => $major_version . '.' . $minor_version . $version_status,
        'semver_format' => $package_pretty_version,
      ];
    }
  }

}
