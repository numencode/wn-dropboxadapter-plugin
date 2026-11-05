# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-11-05

### Added

- **PKCE support**: `dropboxadapter:setup` uses PKCE by default, so the App secret no longer has to be stored on
  the server. The classic flow is still available with `--with-secret`.
- The setup command writes `DROPBOX_APP_KEY` and `DROPBOX_REFRESH_TOKEN` (and `DROPBOX_APP_SECRET` with
  `--with-secret`) directly to `.env` and verifies the connected Dropbox account.
- **Per-disk configuration**: `app_key`, `app_secret`, `refresh_token`, `root`, `cache_store` and `timeout` can be
  set on each `dropbox` disk in `config/filesystems.php`, which allows several Dropbox accounts or folders.
- The `root` disk option sets a folder prefix.
- **Automatic token refresh** in long-running processes (queue workers, large syncs): access tokens are renewed before
  they expire, and a request that fails with `401` is retried once with a fresh token.

### Changed

- Access tokens are cached **encrypted** under a key derived from the credentials (no shared global cache key) and
  for the lifetime reported by Dropbox (`expires_in`) instead of a fixed 3.5 hours.
- All OAuth2 requests use `https://api.dropboxapi.com/oauth2/token` with connection and request timeouts and
  return clear error messages (`DropboxAuthException`).
- The setup command never prints the App secret or tokens, and returns proper exit codes.
- Disk credentials are no longer exposed through `Storage::disk('dropbox')->getConfig()`.
- Internals rewritten into `DropboxOAuth`, `DropboxTokenProvider` and `DropboxDiskFactory` classes; strict types and
  PSR-12 throughout.
- Test suite rewritten: OAuth2 client, token provider (incl. 401 retry through the Dropbox client), disk factory
  and setup command – all without network access.
- Requires **PHP 8.2+**, `spatie/flysystem-dropbox` ^3.1 and `spatie/dropbox-api` ^1.25; supports
  `composer/installers` v1 and v2. Tested with Winter CMS 1.2.15.

### Removed

- **`temp_token` authentication mode** and the `DROPBOX_AUTH_MODE` / `DROPBOX_TEMP_TOKEN` variables.
- Support for PHP 8.0 and 8.1 (end of life).
- `Providers\DropboxServiceProvider` – the disk driver is registered by the plugin itself.

### Upgrading from 1.x

1. Remove `DROPBOX_AUTH_MODE` and `DROPBOX_TEMP_TOKEN` from `.env`.
2. Existing refresh tokens keep working as long as `DROPBOX_APP_SECRET` stays set.
   To switch to PKCE (recommended), run `php artisan dropboxadapter:setup`; it replaces the refresh token and
   clears `DROPBOX_APP_SECRET`.
3. If you used a temporary token, run `php artisan dropboxadapter:setup` to create a refresh token.
4. If you published the plugin config to `config/numencode/dropboxadapter/config.php`, remove `auth_mode` and
   `temp_token` from it.

## [1.1.0] - 2025-07-14

### Added

- **OAuth2 Refresh Token Flow** for Dropbox authentication.
  - Provides a more secure and persistent authentication method.
  - Eliminates the need for manual token renewal every 4 hours.
  - Includes a new console command (`php artisan dropboxadapter:setup`) to generate refresh tokens.
- **Temporary Token Flow** for quick setup and development environments.
- `DROPBOX_AUTH_MODE` to switch between both authentication modes.

## [1.0.0] - 2025-07-08

### Added

- Initial release of the **DropboxAdapter Plugin**.
- Laravel filesystem integration with the Dropbox v2 API.
- Compatible with **Winter CMS v1.2.7** and **PHP 8.0+**.
- Use case: enables Dropbox as a remote storage option for backups, sync operations, and custom filesystem logic.
- Designed for integration with plugins like [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin).

[Unreleased]: https://github.com/numencode/wn-dropboxadapter-plugin/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/numencode/wn-dropboxadapter-plugin/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/numencode/wn-dropboxadapter-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/numencode/wn-dropboxadapter-plugin/releases/tag/v1.0.0
