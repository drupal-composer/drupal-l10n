# drupal-l10n

Composer plugin for automatically downloading Drupal translation files when 
using Composer to manage Drupal projects.

## Usage

This plugin is useful when you want to package your project and then deploy this
package on a target environment and that this environment does not have access
to a localization servers. So you have to prepare the translations before
deploying.

It avoids you to have to put the localization files under your VCS or to have a
local site to download the translations.

## Configuration

You can configure the plugin by providing some settings in the `extra` section
of your root `composer.json`.

```json
{
  "extra": {
    "drupal-l10n": {
      "destination": "translations/contrib",
      "languages": [
        "fr",
        "es"
      ]
    }
  }
}
```

The `destination` parameter may be used to specify the destination folder of the
translation files.

The `languages` parameter specify the languages you want to retrieve.


## Custom command

The plugin by default is only downloading localization files when installing or
updating Drupal core or a Drupal project. If you want to call it manually, you
have to add the command callback to the `scripts`-section of your root
`composer.json`, like this:

```json
{
  "scripts": {
    "drupal-l10n": "DrupalComposer\\DrupalL10n\\Plugin::download"
  }
}
```

After that you can manually download the localization files according to your
configuration by using `composer drupal-l10n`.
