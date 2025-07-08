# Dropbox Adapter Plugin

The **Dropbox Adapter Plugin** provides Dropbox v2 API integration with Winter CMS as a custom filesystem disk driver.
This allows limited usage of Dropbox with Laravel's Storage facade—primarily for custom logic and backup tools such as
[`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin).

[![Version](https://img.shields.io/github/v/release/numencode/wn-dropboxadapter-plugin?style=flat-square&color=0099FF)](https://github.com/numencode/wn-dropboxadapter-plugin/releases)
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/numencode/wn-dropboxadapter-plugin?style=flat-square&color=0099FF)](https://packagist.org/packages/numencode/wn-dropboxadapter-plugin)
[![Checks](https://img.shields.io/github/check-runs/numencode/wn-dropboxadapter-plugin/main?style=flat-square)](https://github.com/numencode/wn-dropboxadapter-plugin/actions)
[![Tests](https://img.shields.io/github/actions/workflow/status/numencode/wn-dropboxadapter-plugin/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/numencode/wn-dropboxadapter-plugin/actions)
[![License](https://img.shields.io/github/license/numencode/wn-dropboxadapter-plugin?label=open%20source&style=flat-square&color=0099FF)](https://github.com/numencode/wn-dropboxadapter-plugin/blob/main/LICENSE.md)

---

## Target Audience

This plugin is designed for developers who want to interact with Dropbox from within Winter CMS using
Laravel’s filesystem abstraction, especially for automation, remote syncing, and backup scenarios.

> **Note:** Due to framework limitations, Winter CMS (as of version 1.2) does not support using Dropbox as a
> filesystem for core `media` or `uploads` disks. This plugin is not intended for direct media asset management,
> but rather for use cases like backup transport, cloud storage sync, or custom plugin integration (e.g. [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin)).

## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require numencode/wn-dropboxadapter-plugin
```

Once the plugin is installed, ensure all migrations are executed:

```bash
php artisan winter:up
```

## Requirements

* [Winter CMS](https://wintercms.com/) version 1.2.7 or newer
* PHP 8.0 or later
* A Dropbox API access token

## Configuration

1. Create a Dropbox App in the [Dropbox App Console](https://www.dropbox.com/developers/apps) and generate an access token.
2. Define a new filesystem disk in your `config/filesystems.php` file:
    ```php
    'dropbox' => [
        'driver' => 'dropbox',
        'authorization_token' => env('DROPBOX_AUTH_TOKEN'),
    ],
    ```
3. Add the token to your `.env` file:
    ```dotenv
    DROPBOX_AUTH_TOKEN=your_generated_token
    ```
    You can now interact with Dropbox programmatically using the Storage facade in Laravel:
    ```php
    Storage::disk('dropbox')->put('backups/site.zip', $contents);
    ```
   This is especially useful for custom automation (e.g., deployment scripts or remote backup workflows).

## Limitations
- **Not compatible with Winter CMS native `media` or `uploads` disks.**
- **Not suitable for asset serving or file uploading through the CMS backend UI.**

Use Dropbox through this plugin **only for custom filesystem operations** that are manually invoked or triggered
via automation (e.g., within the [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin) plugin or similar).

## Example Use Case
This plugin was created to support [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin),
a Winter CMS plugin for managing deployments, backups, and environment synchronization. Dropbox serves as a
remote storage destination for sync packages or archive backups.

---

## Changelog

All notable changes are documented in the [CHANGELOG](CHANGELOG.md).

---

## Contributing

Please refer to the [CONTRIBUTING](CONTRIBUTING.md) guide for details on contributing to this project.

---

## Security

If you identify any security issues, email info@numencode.com rather than using the issue tracker.

---

## Author

The **NumenCode.DropboxAdapter** plugin is created and maintained by [Blaz Orazem](https://orazem.si/).

For inquiries, contact: info@numencode.com

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

[![License](https://img.shields.io/github/license/numencode/wn-dropboxadapter-plugin?style=flat-square&color=0099FF)](https://github.com/numencode/wn-dropboxadapter-plugin/blob/main/LICENSE.md)
