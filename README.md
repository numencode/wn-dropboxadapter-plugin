# Dropbox Adapter Plugin

The **Dropbox Adapter Plugin** adds a `dropbox` filesystem disk to Winter CMS, based on the Dropbox API v2 and
[`spatie/flysystem-dropbox`](https://github.com/spatie/flysystem-dropbox). Use it with Laravel's `Storage` facade for
backups, synchronization and custom integrations such as [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin).

[![Version](https://img.shields.io/github/v/release/numencode/wn-dropboxadapter-plugin?style=flat-square&color=0099FF)](https://github.com/numencode/wn-dropboxadapter-plugin/releases)
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/numencode/wn-dropboxadapter-plugin?style=flat-square&color=0099FF)](https://packagist.org/packages/numencode/wn-dropboxadapter-plugin)
[![Tests](https://img.shields.io/github/actions/workflow/status/numencode/wn-dropboxadapter-plugin/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/numencode/wn-dropboxadapter-plugin/actions)
[![License](https://img.shields.io/github/license/numencode/wn-dropboxadapter-plugin?label=open%20source&style=flat-square&color=0099FF)](https://github.com/numencode/wn-dropboxadapter-plugin/blob/main/LICENSE.md)

---

## Features

- `dropbox` disk driver for Laravel's `Storage` facade.
- Long-lived **OAuth2 refresh tokens**; short-lived access tokens are obtained, cached (encrypted) and renewed
  automatically – also in long-running processes such as queue workers.
- **PKCE** support: the App secret does not need to be stored on the server.
- Guided setup command that writes the credentials to `.env` without displaying them.
- Per-disk configuration: several Dropbox accounts or folders in one project.

## Requirements

- PHP 8.2 or newer
- [Winter CMS](https://wintercms.com) 1.2 (tested with 1.2.15)
- A Dropbox app created in the [Dropbox App Console](https://www.dropbox.com/developers/apps)

> **Note:** This plugin is meant for programmatic file operations (backups, sync, custom logic). It does not support
> Dropbox as the Winter CMS `media` or `uploads` disk, nor serving assets or uploading files through the backend UI.

## Installation

```bash
composer require numencode/wn-dropboxadapter-plugin
```

The plugin has no database tables, so no migrations are needed.

## Configuration

### 1. Create a Dropbox app

In the [Dropbox App Console](https://www.dropbox.com/developers/apps):

1. Create an app with **Scoped access** and choose **App folder** (recommended – the app can only see its own
   folder) or **Full Dropbox**.
2. On the **Permissions** tab, enable the scopes you need, e.g. `files.content.write` and `files.content.read`,
   plus `files.metadata.read` for listing files. Click **Submit**.
3. Copy the **App key** from the **Settings** tab.

### 2. Run the setup command

```bash
php artisan dropboxadapter:setup
```

The command:

1. asks for your **App key**,
2. shows an authorization URL – open it, allow access and paste the **authorization code** back,
3. exchanges the code for a **refresh token** and verifies the connected Dropbox account,
4. writes `DROPBOX_APP_KEY` and `DROPBOX_REFRESH_TOKEN` to your `.env` file.

Tokens and secrets are never displayed. If the configuration is cached, run `php artisan config:cache` afterwards.

By default the command uses **PKCE**, so the App secret is not needed and not stored. If you prefer the classic flow,
use `--with-secret`; the command then also asks for the App secret and writes `DROPBOX_APP_SECRET` to `.env`.

| Option          | Description                                                       |
|-----------------|-------------------------------------------------------------------|
| `--with-secret` | Use the classic flow with the App secret instead of PKCE.         |
| `--force`       | Replace an existing `DROPBOX_REFRESH_TOKEN` without confirmation. |

Re-run the command whenever you need a new refresh token (e.g. after revoking the app's access).

### 3. Define the disk

Add a disk to `config/filesystems.php`:

```php
'disks' => [
    // ...
    'dropbox' => [
        'driver' => 'dropbox',
    ],
],
```

All options are optional; credentials default to the values from `.env`:

| Option          | Default                       | Description                                                         |
|-----------------|-------------------------------|---------------------------------------------------------------------|
| `root`          | `''`                          | Folder prefix for all paths on this disk, e.g. `backups/site-1`.    |
| `app_key`       | `DROPBOX_APP_KEY`             | App key.                                                            |
| `app_secret`    | `DROPBOX_APP_SECRET`          | App secret – only for refresh tokens created with `--with-secret`.  |
| `refresh_token` | `DROPBOX_REFRESH_TOKEN`       | Refresh token.                                                      |
| `cache_store`   | default cache store           | Cache store for the (encrypted) access tokens.                      |
| `timeout`       | `30`                          | Timeout in seconds for token requests.                              |

To use several Dropbox accounts, define one disk per account with its own credentials, e.g. from additional
environment variables:

```php
'dropbox-archive' => [
    'driver'        => 'dropbox',
    'root'          => 'archive',
    'app_key'       => env('DROPBOX_ARCHIVE_APP_KEY'),
    'refresh_token' => env('DROPBOX_ARCHIVE_REFRESH_TOKEN'),
],
```

The defaults themselves can be changed by copying `plugins/numencode/dropboxadapter/config/config.php` to
`config/numencode/dropboxadapter/config.php`.

## Usage

```php
use Illuminate\Support\Facades\Storage;

Storage::disk('dropbox')->put('backups/site.zip', $contents);
Storage::disk('dropbox')->writeStream('backups/db.sql.gz', fopen($localFile, 'r'));

$files = Storage::disk('dropbox')->files('backups');
```

With [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin), pass the disk name as the cloud
storage, e.g. `php artisan syncops:db-push dropbox`.

## Security

- Prefer **PKCE** (the default) – then only the App key and refresh token are stored, and the App secret stays in the
  Dropbox App Console.
- Choose **App folder** access when creating the Dropbox app, so a leaked token cannot access the rest of the
  account.
- Keep `.env` out of version control. The refresh token grants access to your Dropbox until it is revoked
  (Dropbox → Settings → Connected apps, or by regenerating the app).
- Access tokens are cached encrypted with your application key (`APP_KEY`).

If you discover a security issue, please email info@numencode.com instead of using the issue tracker.

## Upgrading from 1.x

Version 2.0 removes the `temp_token` mode and requires PHP 8.2+. See the
[upgrade guide in the changelog](CHANGELOG.md#upgrading-from-1x).

---

## Changelog

All notable changes are documented in the [CHANGELOG](CHANGELOG.md).

## Contributing

Please refer to the [CONTRIBUTING](CONTRIBUTING.md) guide for details on contributing to this project.

## Author

The **NumenCode.DropboxAdapter** plugin is created and maintained by [Blaz Orazem](https://orazem.si/).
More NumenCode plugins: [numencode.com](https://www.numencode.com).

For inquiries, contact: info@numencode.com

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
