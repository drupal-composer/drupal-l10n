<?php

namespace DrupalComposer\DrupalL10n\Tests;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Tests composer plugin functionality.
 */
class PluginTest extends TestCase {

  /**
   * A file system object.
   *
   * @var \Composer\Util\Filesystem
   */
  protected $fs;

  /**
   * A path to temporary directory.
   *
   * @var string
   */
  protected $tmpDir;

  /**
   * The path to this package directory.
   *
   * @var string
   */
  protected $rootDir;

  /**
   * A random string to use as git tag.
   *
   * @var string
   */
  protected $tmpReleaseTag;

  /**
   * SetUp test.
   */
  public function setUp() : void {
    $this->rootDir = realpath(realpath(__DIR__ . '/..'));

    // Prepare temp directory.
    $this->fs = new Filesystem();
    $this->tmpDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'drupal-l10n';
    $this->ensureDirectoryExistsAndClear($this->tmpDir);

    $this->writeTestReleaseTag();
    $this->writeComposerJson();

    chdir($this->tmpDir);
  }

  /**
   * Method tearDown.
   */
  public function tearDown() : void {
    $this->fs->removeDirectory($this->tmpDir);
    $this->git(sprintf('tag -d "%s"', $this->tmpReleaseTag));
  }

  /**
   * Tests a simple composer install and update.
   */
  public function testComposerInstallAndUpdate() {
    $version = '8.9.0';
    $translations_directory = $this->tmpDir . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . 'contrib';
    $core_directory = $this->tmpDir . DIRECTORY_SEPARATOR . 'core';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.fr.po';
    $es_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.es.po';

    $this->assertFileNotExists($fr_translation_file, 'French translations file should not exist.');
    $this->assertFileNotExists($es_translation_file, 'Spanish translations file should not exist.');
    $this->composer('install');
    $this->assertFileExists($this->tmpDir . DIRECTORY_SEPARATOR . 'core', 'Drupal core is installed.');
    $this->assertFileExists($fr_translation_file, 'French translations file should exist.');
    $this->assertFileExists($es_translation_file, 'Spanish translations file should exist.');

    // We remove a downloaded file, so we can check the file was downloaded
    // after the custom command drupal-l10n has been executed.
    $this->fs->remove($fr_translation_file);
    $this->composer('drupal:l10n');
    $this->assertFileExists($fr_translation_file, 'French translations file was downloaded by custom command.');

    // We remove a downloaded file and a package, so we can check the file was
    // downloaded after the command install has been executed.
    $this->fs->remove($fr_translation_file);
    $this->fs->removeDirectory($core_directory);
    $this->composer('install');
    $this->assertFileExists($fr_translation_file, 'French translations file should exist.');

    // Test downloading a new version of the translations.
    $version = '8.9.7';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.fr.po';
    $es_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.es.po';
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $version should not exist.");
    $this->assertFileNotExists($es_translation_file, "Spanish translations file for version: $version should not exist.");
    $this->composer('require --update-with-dependencies drupal/core:"' . $version . '"');
    $this->assertFileExists($fr_translation_file, "French translations file for version: $version should exist.");
    $this->assertFileExists($es_translation_file, "Spanish translations file for version: $version should exist.");

    // Test that the translations for a dev version are not downloaded.
    $version = '8.9.x-dev';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.fr.po';
    $es_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.es.po';
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $version should not exist.");
    $this->assertFileNotExists($es_translation_file, "Spanish translations file for version: $version should not exist.");
    $this->composer('require --update-with-dependencies drupal/core:"' . $version . '"');
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $version should not exist.");
    $this->assertFileNotExists($es_translation_file, "Spanish translations file for version: $version should not exist.");
  }

  /**
   * Tests that contrib modules are handled.
   *
   * Either if using semver or not.
   */
  public function testContribmodules() {
    $core_version = '8.9.0';
    $contrib_module = 'search404';
    $contrib_composer_version = '1.0.0';
    $contrib_drupal_version = '8.x-1.0';
    $translations_directory = $this->tmpDir . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . 'contrib';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . $contrib_module . '-' . $contrib_drupal_version . '.fr.po';

    $this->assertFileNotExists($fr_translation_file, 'French translations file should not exist.');
    $this->composer('install');
    $this->composer('require --update-with-dependencies drupal/core:"' . $core_version . '"');
    $this->composer('require drupal/' . $contrib_module . ':"' . $contrib_composer_version . '"');
    $this->assertFileExists($this->tmpDir . DIRECTORY_SEPARATOR . 'core', 'Drupal core is installed.');
    $this->assertFileExists($fr_translation_file, 'French translations file should exist.');

    // Test downloading a semantic version of the module.
    $contrib_composer_version = '2.0.0';
    $contrib_drupal_version = '2.0.0';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . $contrib_module . '-' . $contrib_drupal_version . '.fr.po';
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $contrib_drupal_version should not exist.");
    $this->composer('require drupal/' . $contrib_module . ':"' . $contrib_composer_version . '"');
    $this->assertFileExists($fr_translation_file, "French translations file for version: $contrib_drupal_version should exist.");
  }

  /**
   * Tests that on Drupal 9, core and contrib modules are handled.
   *
   * Either if using semver or not.
   */
  public function testDrupal9() {
    $core_version = '9.1.3';
    $contrib_module = 'entity_share';
    $contrib_composer_version = '3.0.0-beta2';
    $contrib_drupal_version = '8.x-3.0-beta2';
    $semver_contrib_module = 'entity_share_cron';
    $semver_contrib_composer_version = '3.0.0-beta1';
    $semver_contrib_drupal_version = '3.0.0-beta1';
    $translations_directory = $this->tmpDir . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . 'contrib';
    $core_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $core_version . '.fr.po';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . $contrib_module . '-' . $contrib_drupal_version . '.fr.po';
    $semver_fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . $semver_contrib_module . '-' . $semver_contrib_drupal_version . '.fr.po';

    $this->assertFileNotExists($core_translation_file, 'French translations file should not exist.');
    $this->assertFileNotExists($fr_translation_file, 'French translations file should not exist.');
    $this->assertFileNotExists($semver_fr_translation_file, 'French translations file should not exist.');
    $this->composer('install');
    $this->composer('require --update-with-dependencies drupal/core:"' . $core_version . '"');
    $this->composer('require drupal/' . $contrib_module . ':"' . $contrib_composer_version . '" drupal/' . $semver_contrib_module . ':"' . $semver_contrib_composer_version . '"');
    $this->assertFileExists($core_translation_file, 'French translations file should exist.');
    $this->assertFileExists($fr_translation_file, 'French translations file should exist.');
    $this->assertFileExists($semver_fr_translation_file, 'French translations file should exist.');
  }

  /**
   * Tests that on Drupal 7, core and contrib modules are handled.
   */
  public function testDrupal7() {
    $core_version = '7.78.0';
    $contrib_module = 'views';
    $contrib_composer_version = '3.24.0';
    $contrib_drupal_version = '7.x-3.24';
    $translations_directory = $this->tmpDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . 'contrib';
    $core_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $core_version . '.fr.po';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . $contrib_module . '-' . $contrib_drupal_version . '.fr.po';

    $this->assertFileNotExists($core_translation_file, 'French translations file should not exist.');
    $this->assertFileNotExists($fr_translation_file, 'French translations file should not exist.');
    $this->composer('install');
    $this->composer('remove drupal/core');
    // Set Drupal repository to target Drupal 7.
    $this->composer('config repositories.drupal composer https://packages.drupal.org/7');
    $this->composer('require drupal/drupal:"' . $core_version . '"');
    $this->composer('require drupal/' . $contrib_module . ':"' . $contrib_composer_version . '"');
    $this->assertFileExists($core_translation_file, 'French translations file should exist.');
    $this->assertFileExists($fr_translation_file, 'French translations file should exist.');
  }

  /**
   * Writes the default composer json to the temp directory.
   */
  protected function writeComposerJson() {
    $json = json_encode($this->composerJsonDefaults(), JSON_PRETTY_PRINT);
    // Write composer.json.
    file_put_contents($this->tmpDir . '/composer.json', $json);
  }

  /**
   * Writes a tag for the current commit.
   *
   * So we can reference it directly in the composer.json.
   */
  protected function writeTestReleaseTag() {
    // Tag the current state.
    $this->tmpReleaseTag = '999.0.' . time();
    $this->git(sprintf('tag -a "%s" -m "%s"', $this->tmpReleaseTag, 'Tag for testing this exact commit'));
  }

  /**
   * Provides the default composer.json data.
   *
   * @return array
   *   An array to be transformed into JSON.
   */
  protected function composerJsonDefaults() {
    return [
      'repositories' => [
        'this_package' => [
          'type' => 'vcs',
          'url' => $this->rootDir,
        ],
        'drupal' => [
          'type' => 'composer',
          'url' => 'https://packages.drupal.org/8',
        ],
      ],
      'require' => [
        'drupal-composer/drupal-l10n' => $this->tmpReleaseTag,
        'composer/installers' => '^1.2',
        'drupal/core' => '8.9.0',
      ],
      'extra' => [
        'drupal-l10n' => [
          'destination' => 'translations/contrib',
          'languages' => [
            'fr',
            'es',
          ],
        ],
      ],
      'minimum-stability' => 'dev',
      'prefer-stable' => TRUE,
    ];
  }

  /**
   * Wrapper for the composer command.
   *
   * @param string $command
   *   Composer command name, arguments and/or options.
   *
   * @throws \Exception
   *   Throws an exception if there is an error during composer command
   *   execution.
   */
  protected function composer($command) {
    chdir($this->tmpDir);
    passthru(escapeshellcmd($this->rootDir . '/vendor/bin/composer ' . $command), $exit_code);
    if ($exit_code !== 0) {
      throw new \Exception('Composer returned a non-zero exit code');
    }
  }

  /**
   * Wrapper for git command in the root directory.
   *
   * @param string $command
   *   Git command name, arguments and/or options.
   *
   * @throws \Exception
   *   Throws an exception if there is an error during git command execution.
   */
  protected function git($command) {
    chdir($this->rootDir);
    passthru(escapeshellcmd('git ' . $command), $exit_code);
    if ($exit_code !== 0) {
      throw new \Exception('Git returned a non-zero exit code');
    }
  }

  /**
   * Makes sure the given directory exists and has no content.
   *
   * @param string $directory
   *   The directory to check.
   */
  protected function ensureDirectoryExistsAndClear($directory) {
    if (is_dir($directory)) {
      $this->fs->removeDirectory($directory);
    }
    mkdir($directory, 0777, TRUE);
  }

}
