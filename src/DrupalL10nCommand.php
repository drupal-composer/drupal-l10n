<?php

namespace DrupalComposer\DrupalL10n;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "drupal:l10n" command class.
 *
 * Downloads translations files.
 */
class DrupalL10nCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    parent::configure();
    $this
      ->setName('drupal:l10n')
      ->setDescription('Download Drupal translations files.')
      ->setDefinition([
        new InputOption('no-dev', NULL, InputOption::VALUE_NONE, 'Disables download of translations of require-dev dependencies.'),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $handler = new Handler($this->getComposer(), $this->getIO());
    $handler->downloadLocalization(!$input->getOption('no-dev'));
  }

}
