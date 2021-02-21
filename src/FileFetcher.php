<?php

namespace DrupalComposer\DrupalL10n;

use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;

/**
 * File fetcher.
 */
class FileFetcher {

  /**
   * The input output interface.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * The HTTP downloader.
   *
   * @var \Composer\Util\HttpDownloader
   */
  protected $httpDownloader;

  /**
   * An array of options containing the languages and the destination directory.
   *
   * @var array
   */
  protected $options;

  /**
   * The Drupal major version.
   *
   * @var string
   */
  protected $coreMajorVersion;

  /**
   * The local file system.
   *
   * @var \Composer\Util\Filesystem
   */
  protected $fs;

  /**
   * A boolean indicating if progress should be displayed.
   *
   * @var bool
   */
  protected $progress;

  /**
   * FileFetcher constructor.
   *
   * @param \Composer\IO\IOInterface $io
   *   The input output interface.
   * @param \Composer\Util\HttpDownloader $http_downloader
   *   The remote file system.
   * @param array $options
   *   The composer plugin options.
   * @param string $core_version
   *   The Drupal core complete version.
   * @param bool $progress
   *   If the command has the progress displayed or not.
   */
  public function __construct(IOInterface $io, HttpDownloader $http_downloader, array $options, $core_version, $progress) {
    $this->io = $io;
    $this->httpDownloader = $http_downloader;
    $this->options = $options;
    $this->coreMajorVersion = substr($core_version, 0, 1);
    $this->fs = new Filesystem();
    $this->progress = $progress;
  }

  /**
   * Fetch files.
   *
   * @param array $drupal_projects
   *   The list of Drupal project to download.
   * @param string $destination
   *   The destination path.
   */
  public function fetch(array $drupal_projects, $destination) {
    $this->fs->ensureDirectoryExists($destination);

    foreach ($drupal_projects as $package_name => $drupal_version_formats) {
      $number_of_formats = count($drupal_version_formats);
      preg_match("/^.*\/(.*)$/", $package_name, $parsed_drupal_project_name);
      foreach ($this->options['languages'] as $langcode) {
        $exception_count = 0;
        foreach ($drupal_version_formats as $format => $drupal_version) {
          $filename = $this->getFilename($package_name, $drupal_version, $parsed_drupal_project_name[1], $langcode, $format);
          $url = $this->getUrl($package_name, $parsed_drupal_project_name[1], $filename);

          // Fetch the file.
          try {
            if ($this->progress) {
              $this->io->write("  - <info>$filename</info> (<comment>$url</comment>): ", FALSE);
            }

            $this->httpDownloader->copy($url, $destination . '/' . $filename);

            if ($this->progress) {
              // Used to put a new line because the remote file system does not
              // put one.
              $this->io->write('');
            }
            // The file has been downloaded. No need to try other format.
            break;
          }
          catch (TransportException $transportException) {
            $exception_count++;
            // Used to put a new line because the remote file system does not put
            // one.
            $this->io->write('');
          }
        }

        // All URLs failed.
        if ($exception_count == $number_of_formats) {
          $this->io->writeError('Could not download the file in any managed formats. This is certainly due to a non-existing translation file.');
        }
      }
    }
  }

  /**
   * Helper function to prepare a localization filename.
   *
   * @param string $package_name
   *   The package name.
   * @param string $drupal_version
   *   The Drupal version of the package.
   * @param string $drupal_project_name
   *   The Drupal project name.
   * @param string $langcode
   *   The langcode.
   * @param string $version_format
   *   The format version. Either semver_format or drupal_format.
   *
   * @return string
   *   The prepared URL.
   */
  protected function getFilename($package_name, $drupal_version, $drupal_project_name, $langcode, $version_format) {
    // Special case for Drupal core.
    if (in_array($package_name, ['drupal/core', 'drupal/drupal'])) {
      return 'drupal-' . $drupal_version . '.' . $langcode . '.po';
    }
    else {
      $core_major_version = $this->coreMajorVersion;
      // Starting from 8.x, translations are in
      // https://ftp.drupal.org/files/translations/all/ even for Drupal 9.
      // And we make the assumption that only a few contrib projects have a 9.x
      // branch and will make a semver branch.
      if ($core_major_version >= 8) {
        $core_major_version = 8;
      }

      // Old Drupal format.
      if ($version_format == 'drupal_format') {
        return $drupal_project_name . '-' . $core_major_version . '.x-' . $drupal_version . '.' . $langcode . '.po';
      }
      // Semver format.
      else {
        return $drupal_project_name . '-' . $drupal_version . '.' . $langcode . '.po';
      }
    }
  }

  /**
   * Helper function to prepare a localization file URL.
   *
   * @param string $package_name
   *   The package name.
   * @param string $drupal_project_name
   *   The Drupal project name.
   * @param string $filename
   *   The translation file name.
   *
   * @return string
   *   The prepared URL.
   */
  protected function getUrl($package_name, $drupal_project_name, $filename) {
    $core_folder = 'all';
    // Starting from 8.x, translations are in
    // https://ftp.drupal.org/files/translations/all/ even for Drupal 9.
    // Otherwise it is https://ftp.drupal.org/files/translations/7.x/.
    if ($this->coreMajorVersion < 8) {
      $core_folder = $this->coreMajorVersion . '.x';
    }

    // Special case for Drupal core.
    if (in_array($package_name, ['drupal/core', 'drupal/drupal'])) {
      return 'https://ftp.drupal.org/files/translations/' . $core_folder . '/drupal/' . $filename;
    }
    else {
      return 'https://ftp.drupal.org/files/translations/' . $core_folder . '/' . $drupal_project_name . '/' . $filename;
    }
  }

}
