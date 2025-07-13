# Dropbox Adapter Plugin

The **Dropbox Adapter Plugin** provides Dropbox v2 API integration with Winter CMS as a custom filesystem disk driver.
This allows limited usage of Dropbox with Laravel's Storage facade — primarily for custom logic and backup tools such as
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

---

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

- [Winter CMS](https://wintercms.com) version 1.2.7 or newer
- PHP 8.0 or later
- A Dropbox App (created in the [Dropbox App Console](https://www.dropbox.com/developers/apps))
  - For **Refresh Token Flow**: You'll need your `App key` and `App secret`.
  - For **Temporary Token Flow**: You'll need a manually generated `Access token`.

## Configuration and Authentication

This plugin offers two methods for authenticating with the Dropbox API: the **Refresh Token Flow** (recommended for
production and long-term use) and the **Temporary Token Flow** (ideal for quick development or testing).
You can switch between these modes in your `.env` file.

### 1. Create a Dropbox App

Regardless of the authentication method, you need to [create a Dropbox App](https://www.dropbox.com/developers/apps).

When creating your app:

- Choose **"Scoped access"** and select the permissions your application needs (e.g.,
  `files.content.write`, `files.content.read` for read/write access).

### 2. Choose your authentication method

You specify the authentication method in your `.env` file using the `DROPBOX_AUTH_MODE` variable.
You can choose between `refresh_token` and `temp_token` modes.

#### a. Refresh Token Flow (recommended for production)

This is the **secure and persistent** way to authenticate. It uses a long-lived **refresh token** to automatically
obtain new, short-lived **access tokens** as needed, eliminating the need for manual intervention when tokens expire.

##### Setup Steps

1. **Add Dropbox App credentials to `.env`**:

   You'll need your Dropbox `App key` and `App secret`.
    ```dotenv
    DROPBOX_AUTH_MODE=refresh_token
    DROPBOX_APP_KEY=
    DROPBOX_APP_SECRET=
    DROPBOX_REFRESH_TOKEN=
    ```

2. **Generate your refresh token**:

   Use the provided console command to go through the OAuth2 authorization process and obtain your refresh token:
    ```bash
    php artisan dropboxadapter:setup
    ```

   Follow the on-screen prompts:
    - It will ask for your **Dropbox App key**.
    - It will provide a URL to open in your browser. Authorize your app on Dropbox. After authorization, Dropbox will
      provide an "authorization code" (often in the URL parameters if no redirect URI is set, or directly on the
      success page). Copy this `code`.
    - Paste the `authorization code` back into the console.
    - Enter your **Dropbox App secret**.
    - The command will then exchange this code for a refresh token and display it.

3. **Update `.env` with refresh token**:

   Copy the generated refresh token and add it to your `.env` file.

#### b. Temporary Token Flow (for development/testing only)

This method is quick and easy for **temporary testing**, but the token will **expire after 4 hours** and requires
manual renewal. **Do not use this in production.**

##### Setup Steps

1. **Generate a temporary access token:**

   - Go to your [Dropbox App Console](https://www.dropbox.com/developers/apps).
   - Navigate to your app's settings.
   - Under "OAuth 2", find the "Generated access token" section and click "Generate". Copy this token.

2. **Add temporary token to `.env`:**

    ```dotenv
    DROPBOX_AUTH_MODE=temp_token
    DROPBOX_TEMP_TOKEN=
    ```

    **Important:** If using this mode, the `DROPBOX_APP_KEY`, `DROPBOX_APP_SECRET`,
    and `DROPBOX_REFRESH_TOKEN` variables are ignored by the plugin.

### 3. Define the dropbox disk

Once you have configured your chosen authentication method in `.env`,
define a new filesystem disk in your `config/filesystems.php` file:

```php
'dropbox' => [
    'driver' => 'dropbox',
],
```

### 4. Usage with Storage Facade

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
