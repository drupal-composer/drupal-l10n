<?php

namespace DrupalComposer\DrupalL10n\Tests;

use Composer\Util\Filesystem;

/**
 * Tests composer plugin functionality.
 */
class PluginTest extends \PHPUnit_Framework_TestCase {

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
  public function setUp() {
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
  public function tearDown() {
    $this->fs->removeDirectory($this->tmpDir);
    $this->git(sprintf('tag -d "%s"', $this->tmpReleaseTag));
  }

  /**
   * Tests a simple composer install and update.
   */
  public function testComposerInstallAndUpdate() {
    $version = '8.3.0';
    $translations_directory = $this->tmpDir . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . 'contrib';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.fr.po';
    $es_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.es.po';

    $this->assertFileNotExists($fr_translation_file, 'French translations file should not exist.');
    $this->assertFileNotExists($es_translation_file, 'Spanish translations file should not exist.');
    $this->composer('install');
    $this->assertFileExists($this->tmpDir . DIRECTORY_SEPARATOR . 'core', 'Drupal core is installed.');
    $this->assertFileExists($fr_translation_file, 'French translations file should exist.');
    $this->assertFileExists($es_translation_file, 'Spanish translations file should exist.');

    // We touch a downloaded file, so we can check the file was modified after
    // the custom command drupal-l10n has been executed.
    touch($fr_translation_file);
    clearstatcache();
    $mtime_touched = filemtime($fr_translation_file);
    $this->composer('drupal-l10n');
    clearstatcache();
    $mtime_after = filemtime($fr_translation_file);
    $this->assertNotEquals($mtime_after, $mtime_touched, 'French translations file was modified by custom command.');

    // Test downloading a new version of the translations.
    $version = '8.3.1';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.fr.po';
    $es_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.es.po';
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $version should not exist.");
    $this->assertFileNotExists($es_translation_file, "Spanish translations file for version: $version should not exist.");
    $this->composer('require --update-with-dependencies drupal/core:"' . $version . '"');
    $this->assertFileExists($fr_translation_file, "French translations file for version: $version should exist.");
    $this->assertFileExists($es_translation_file, "Spanish translations file for version: $version should exist.");

    // Test that the translations for a dev version are not downloaded.
    $version = '8.3.x-dev';
    $fr_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.fr.po';
    $es_translation_file = $translations_directory . DIRECTORY_SEPARATOR . 'drupal-' . $version . '.es.po';
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $version should not exist.");
    $this->assertFileNotExists($es_translation_file, "Spanish translations file for version: $version should not exist.");
    $this->composer('require --update-with-dependencies drupal/core:"' . $version . '"');
    $this->assertFileNotExists($fr_translation_file, "French translations file for version: $version should not exist.");
    $this->assertFileNotExists($es_translation_file, "Spanish translations file for version: $version should not exist.");
  }

  /**
   * Writes the default composer json to the temp direcoty.
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
        [
          'type' => 'vcs',
          'url' => $this->rootDir,
        ],
      ],
      'require' => [
        'drupal-composer/drupal-l10n' => $this->tmpReleaseTag,
        'composer/installers' => '^1.0.20',
        'drupal/core' => '8.3.0',
      ],
      'scripts' => [
        'drupal-l10n' => 'DrupalComposer\\DrupalL10n\\Plugin::download',
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
