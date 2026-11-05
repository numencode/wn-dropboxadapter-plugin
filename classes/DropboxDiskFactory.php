<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Classes;

use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;

/**
 * Creates the "dropbox" filesystem disk.
 *
 * Disk options (config/filesystems.php) take precedence over the plugin configuration
 * (config/numencode/dropboxadapter/config.php or the DROPBOX_* environment variables):
 *
 *     'dropbox' => [
 *         'driver'        => 'dropbox',
 *         'root'          => 'backups',      // optional folder prefix
 *         'app_key'       => '...',          // optional, defaults to DROPBOX_APP_KEY
 *         'app_secret'    => '...',          // optional, only for non-PKCE refresh tokens
 *         'refresh_token' => '...',          // optional, defaults to DROPBOX_REFRESH_TOKEN
 *         'cache_store'   => 'file',         // optional cache store for access tokens
 *         'timeout'       => 30,             // optional OAuth2 request timeout in seconds
 *     ],
 */
class DropboxDiskFactory
{
    /**
     * Options that hold credentials and must not be exposed through the disk's config.
     */
    protected const SECRET_OPTIONS = ['app_key', 'app_secret', 'refresh_token'];

    /**
     * @param array<string, mixed> $config
     *
     * @throws DropboxAuthException
     */
    public function __invoke(Container $app, array $config): FilesystemAdapter
    {
        $tokenProvider = $this->makeTokenProvider($app, $config);

        $adapter = new DropboxAdapter(
            new DropboxClient($tokenProvider),
            trim((string) ($config['root'] ?? ''), '/')
        );

        $diskConfig = Arr::except($config, self::SECRET_OPTIONS);

        return new FilesystemAdapter(new Filesystem($adapter, $diskConfig), $adapter, $diskConfig);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws DropboxAuthException
     */
    public function makeTokenProvider(Container $app, array $config): DropboxTokenProvider
    {
        $option = fn (string $key, mixed $default = null): mixed =>
            ($config[$key] ?? null) ?: $app['config']->get('numencode.dropboxadapter::' . $key, $default);

        $appKey = (string) $option('app_key');
        $refreshToken = (string) $option('refresh_token');
        $appSecret = $option('app_secret') ?: null;

        if ($appKey === '' || $refreshToken === '') {
            throw new DropboxAuthException(
                'The Dropbox disk is not configured: DROPBOX_APP_KEY and DROPBOX_REFRESH_TOKEN are required. '
                . 'Run "php artisan dropboxadapter:setup" to create them.'
            );
        }

        return new DropboxTokenProvider(
            new DropboxOAuth($app->make(HttpFactory::class), (int) $option('timeout', 30)),
            $app['cache']->store($option('cache_store')),
            $app['encrypter'],
            $appKey,
            $refreshToken,
            $appSecret,
        );
    }
}
