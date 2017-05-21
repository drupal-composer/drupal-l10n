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
use Composer\Script\Event;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;

/**
 * Handler that do the actual stuff.
 */
class Handler {

  const PRE_DRUPAL_L10N_CMD = 'pre-drupal-l10n-cmd';
  const POST_DRUPAL_L10N_CMD = 'post-drupal-l10n-cmd';

  /**
   * @var \Composer\Composer
   */
  protected $composer;

  /**
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
   * Handler constructor.
   *
   * @param Composer $composer
   * @param IOInterface $io
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * Helper function to get the Drupal core package.
   *
   * @param \Composer\DependencyResolver\Operation\OperationInterface $operation
   *   The current operation object.
   *
   * @return null|\Composer\Package\PackageInterface
   *   Returns the Drupal core package if found, NULL otherwise.
   */
  protected function getCorePackage(OperationInterface $operation) {
    if ($operation instanceof InstallOperation) {
      $package = $operation->getPackage();
    }
    elseif ($operation instanceof UpdateOperation) {
      $package = $operation->getTargetPackage();
    }
    if (isset($package) && $package instanceof PackageInterface && $package->getName() == 'drupal/core') {
      return $package;
    }
    return NULL;
  }

  /**
   * Marks scaffolding to be processed after an install or update command.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   A package event.
   */
  public function onPostPackageEvent(PackageEvent $event) {
//    $package = $this->getCorePackage($event->getOperation());
//    if ($package) {
//      // By explicitly setting the core package, the onPostCmdEvent() will
//      // process the scaffolding automatically.
//      $this->drupalCorePackage = $package;
//    }
  }

  /**
   * Post install command event to execute the scaffolding.
   *
   * @param \Composer\Script\Event $event
   *   A composer event.
   */
  public function onPostCmdEvent(Event $event) {
//    // Only install the scaffolding if drupal/core was installed,
//    // AND there are no scaffolding files present.
//    if (isset($this->drupalCorePackage)) {
//      $this->downloadLocalization();
//    }
  }

  /**
   * Downloads drupal localization files for the current process.
   */
  public function downloadLocalization() {
    $drupalCorePackage = $this->getDrupalCorePackage();
    $webroot = realpath($this->getWebRoot());

    // Collect options, excludes and settings files.
    $options = $this->getOptions();
    //    $files = array_diff($this->getIncludes(), $this->getExcludes());


    //    'source' => 'http://cgit.drupalcode.org/drupal/plain/{path}?h={version}',
    //    http://ftp.drupal.org/files/translations/8.x/ctools/ctools-8.x-3.0.fr.po
    //    http://ftp.drupal.org/files/translations/{core_major_version}.x/{project_name}/{project_name}-{project_version}.{language}.po

    // Need to convert composer project version to Drupal contrib version system.
    // 3.0.0 => 8.x-3.0

    // Call any pre-l10n scripts that may be defined.
    $dispatcher = new EventDispatcher($this->composer, $this->io);
    $dispatcher->dispatch(self::PRE_DRUPAL_L10N_CMD);

    $version = $this->getDrupalCoreVersion($drupalCorePackage);

    var_dump($options);
    //    $remoteFs = new RemoteFilesystem($this->io);

    //    $fetcher = new FileFetcher($remoteFs, $options['source'], $files, $this->io, $this->composer->getConfig());
    //    $fetcher->fetch($version, $webroot);

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
      $this->drupalCorePackage = $this->getPackage('drupal/core');
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
    // Webroot is the parent path of the drupal core installation path.
    $webroot = dirname($corePath);

    return $webroot;
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

}
