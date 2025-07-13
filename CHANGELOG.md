# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-07-14
### Changes
- Implemented **OAuth2 Refresh Token Flow** for Dropbox authentication.
  - Provides a more secure and persistent authentication method.
  - Eliminates the need for manual token renewal every 4 hours.
  - Includes a new console command (`php artisan dropboxadapter:setup`) to generate and manage refresh tokens.
- Introduced **Temporary Token Flow** for quick setup and development environments.
  - Allows direct use of short-lived access tokens for testing.
- Enhanced `.env` configuration to support both authentication modes via `DROPBOX_AUTH_MODE`.

## [1.0.0] - 2025-07-08
### Added
- Initial release of the **DropboxAdapter Plugin**.
- Laravel filesystem integration with the Dropbox v2 API.
- Fully compatible with **Winter CMS v1.2.7** and **PHP 8.0+**.
- Use case: enables Dropbox as a remote storage option for backups, sync operations, and custom filesystem logic.
- Designed for integration with plugins like [`NumenCode.SyncOps`](https://github.com/numencode/wn-syncops-plugin).

### Notes
- **Not compatible** with `media` or `uploads` disks in Winter CMS.
- Intended for developer-oriented scenarios such as automation, deployment, or remote sync — not general file management.

---

### Links
- GitHub repository: [wn-dropboxadapter-plugin](https://github.com/numencode/wn-dropboxadapter-plugin)
