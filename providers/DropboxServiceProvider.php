<?php namespace NumenCode\DropboxAdapter\Providers;

use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;
use Illuminate\Filesystem\FilesystemAdapter;

class DropboxServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('dropbox', function ($app, $config) {
            // Read from plugin config, which reads from .env by default
            $authMode = Config::get('numencode.dropboxadapter::auth_mode', 'refresh_token');

            $accessToken = null;

            if ($authMode === 'temp_token') {
                $accessToken = Config::get('numencode.dropboxadapter::temp_token');

                if (!$accessToken) {
                    throw new \InvalidArgumentException('DROPBOX_TEMP_TOKEN is required when DROPBOX_AUTH_MODE is set to \'temp_token\'.');
                }
            } else {
                if (!$accessToken = Cache::get('dropbox_access_token')) {
                    $appKey = Config::get('numencode.dropboxadapter::app_key');
                    $appSecret = Config::get('numencode.dropboxadapter::app_secret');
                    $refreshToken = Config::get('numencode.dropboxadapter::refresh_token');

                    if (empty($refreshToken) || empty($appKey) || empty($appSecret)) {
                        throw new \InvalidArgumentException('Missing Dropbox configuration for refresh token flow (DROPBOX_REFRESH_TOKEN, DROPBOX_APP_KEY, or DROPBOX_APP_SECRET).');
                    }

                    $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
                        'grant_type'    => 'refresh_token',
                        'refresh_token' => $refreshToken,
                        'client_id'     => $appKey,
                        'client_secret' => $appSecret,
                    ]);

                    $response->throw();
                    $data = $response->json();
                    $accessToken = $data['access_token'];

                    Cache::put('dropbox_access_token', $accessToken, 12600);
                }
            }

            if (!$accessToken) {
                throw new \RuntimeException('Failed to obtain a Dropbox access token.');
            }

            $adapter = new DropboxAdapter(
                new DropboxClient($accessToken)
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
