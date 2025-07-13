<?php

return [
    /*
     * The authentication mode for Dropbox.
     * Can be 'refresh_token' (recommended for production) or 'temp_token' (for quick testing).
     */
    'auth_mode' => env('DROPBOX_AUTH_MODE', 'refresh_token'),

    /*
     * Your Dropbox App Key
     */
    'app_key' => env('DROPBOX_APP_KEY'),

    /*
     * Your Dropbox App Secret
     */
    'app_secret' => env('DROPBOX_APP_SECRET'),

    /*
     * The Dropbox refresh token obtained via the `dropbox:setup` command.
     */
    'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),

    /*
     * A temporary Dropbox access token for testing purposes.
     * Only used if 'auth_mode' is set to 'temp_token'.
     */
    'temp_token' => env('DROPBOX_TEMP_TOKEN'),
];
