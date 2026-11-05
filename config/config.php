<?php

/*
 * Default credentials for "dropbox" disks. Every option can be overridden per disk in config/filesystems.php.
 * To customize these defaults without touching the plugin, copy this file to
 * config/numencode/dropboxadapter/config.php.
 */
return [
    /*
     * The App key of your Dropbox app (https://www.dropbox.com/developers/apps).
     */
    'app_key' => env('DROPBOX_APP_KEY'),

    /*
     * The App secret. Only needed for refresh tokens created with
     * "php artisan dropboxadapter:setup --with-secret". Leave empty when using PKCE (the default).
     */
    'app_secret' => env('DROPBOX_APP_SECRET'),

    /*
     * The long-lived refresh token, created with "php artisan dropboxadapter:setup".
     */
    'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),

    /*
     * Cache store used for the short-lived access tokens (null = default cache store).
     * Access tokens are stored encrypted with the application key.
     */
    'cache_store' => null,

    /*
     * Timeout in seconds for requests to the Dropbox OAuth2 endpoint.
     */
    'timeout' => 30,
];
