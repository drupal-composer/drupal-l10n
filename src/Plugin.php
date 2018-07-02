<?php

namespace DrupalComposer\DrupalL10n;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin for handling drupal translations.
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * Handler object that do the actual logic.
   *
   * @var \DrupalComposer\DrupalL10n\Handler
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    // We use a separate PluginScripts object. This way we separate
    // functionality and also avoid some debug issues with the plugin being
    // copied on initialisation.
    // @see \Composer\Plugin\PluginManager::registerPackage()
    $this->handler = new Handler($composer, $io);
  }

  /**
   * {@inheritdoc}
   */
  public function getCapabilities() {
    return [
      'Composer\Plugin\Capability\CommandProvider' => 'DrupalComposer\DrupalL10n\CommandProvider',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'postPackage',
      PackageEvents::POST_PACKAGE_UPDATE => 'postPackage',
      ScriptEvents::POST_UPDATE_CMD => 'postCmd',
      PluginEvents::COMMAND => 'cmdBegins',
    ];
  }

  /**
   * Command begins event callback.
   *
   * @param \Composer\Plugin\CommandEvent $event
   *   The command event.
   */
  public function cmdBegins(CommandEvent $event) {
    $this->handler->onCmdBeginsEvent($event);
  }

  /**
   * Post package event behaviour.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   A package event object.
   */
  public function postPackage(PackageEvent $event) {
    $this->handler->onPostPackageEvent($event);
  }

  /**
   * Post command event callback.
   *
   * @param \Composer\Script\Event $event
   *   A composer script event object.
   */
  public function postCmd(Event $event) {
    $this->handler->onPostCmdEvent($event);
  }

}
