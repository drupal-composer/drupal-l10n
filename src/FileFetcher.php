<?php

namespace DrupalComposer\DrupalL10n;

use Composer\Downloader\TransportException;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;

/**
 * File fetcher.
 */
class FileFetcher {

  /**
   * The remote file system.
   *
   * @var \Composer\Util\RemoteFilesystem
   */
  protected $remoteFilesystem;

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
   * FileFetcher constructor.
   *
   * @param \Composer\Util\RemoteFilesystem $remoteFilesystem
   *   The remote file system.
   * @param array $options
   *   The composer plugin options.
   * @param string $core_version
   *   The Drupal core complete version.
   */
  public function __construct(RemoteFilesystem $remoteFilesystem, array $options, $core_version) {
    $this->remoteFilesystem = $remoteFilesystem;
    $this->options = $options;
    $this->coreMajorVersion = substr($core_version, 0, 1);
    $this->fs = new Filesystem();
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

    foreach ($drupal_projects as $package_name => $drupal_version) {
      preg_match("/^.*\/(.*)$/", $package_name, $parsed_drupal_project_name);
      foreach ($this->options['languages'] as $langcode) {
        $filename = $this->getFilename($package_name, $drupal_version, $parsed_drupal_project_name[1], $langcode);
        $url = $this->getUrl($package_name, $drupal_version, $parsed_drupal_project_name[1], $langcode);

        // Fetch the file.
        try {
          $this->remoteFilesystem->copy($url, $url, $destination . '/' . $filename);
        }
        catch (TransportException $transportException) {
          // TODO: print which file is downloaded, especially if there is an
          // error. Certainly due to a non-existing translation file.
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
   *
   * @return string
   *   The prepared URL.
   */
  protected function getFilename($package_name, $drupal_version, $drupal_project_name, $langcode) {
    // Special case for Drupal core.
    if ($package_name == 'drupal/core') {
      return 'drupal-' . $drupal_version . '.' . $langcode . '.po';
    }
    else {
      return $drupal_project_name . '-' . $this->coreMajorVersion . '.x-' . $drupal_version . '.' . $langcode . '.po';
    }
  }

  /**
   * Helper function to prepare a localization file URL.
   *
   * @param string $package_name
   *   The package name.
   * @param string $drupal_version
   *   The Drupal version of the package.
   * @param string $drupal_project_name
   *   The Drupal project name.
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The prepared URL.
   */
  protected function getUrl($package_name, $drupal_version, $drupal_project_name, $langcode) {
    // Special case for Drupal core.
    if ($package_name == 'drupal/core') {
      return 'http://ftp.drupal.org/files/translations/' . $this->coreMajorVersion . '.x/drupal/drupal-' . $drupal_version . '.' . $langcode . '.po';
    }
    else {
      return 'http://ftp.drupal.org/files/translations/' . $this->coreMajorVersion . '.x/' . $drupal_project_name . '/' . $drupal_project_name . '-' . $this->coreMajorVersion . '.x-' . $drupal_version . '.' . $langcode . '.po';
    }
  }

}
