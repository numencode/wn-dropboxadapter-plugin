<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Tests\Classes;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use NumenCode\DropboxAdapter\Classes\DropboxAuthException;
use NumenCode\DropboxAdapter\Classes\DropboxDiskFactory;
use NumenCode\DropboxAdapter\Classes\DropboxOAuth;
use Spatie\FlysystemDropbox\DropboxAdapter;
use System\Tests\Bootstrap\PluginTestCase;

class DropboxDiskFactoryTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Config::set('numencode.dropboxadapter::app_key', 'plugin-key');
        Config::set('numencode.dropboxadapter::app_secret', null);
        Config::set('numencode.dropboxadapter::refresh_token', 'plugin-refresh');
        Config::set('numencode.dropboxadapter::cache_store', null);
        Config::set('numencode.dropboxadapter::timeout', 30);

        Http::fake([DropboxOAuth::TOKEN_URL => Http::response(['access_token' => 'access', 'expires_in' => 14400])]);
    }

    protected function setDisk(array $config): void
    {
        Config::set('filesystems.disks.dropbox', array_merge(['driver' => 'dropbox'], $config));
        Storage::forgetDisk('dropbox');
    }

    public function testPluginRegistersDropboxDriver(): void
    {
        $this->setDisk([]);

        $disk = Storage::disk('dropbox');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(DropboxAdapter::class, $disk->getAdapter());
    }

    public function testDiskCreationDoesNotContactDropbox(): void
    {
        $this->setDisk([]);

        Storage::disk('dropbox');

        Http::assertNothingSent();
    }

    public function testCredentialsAreNotExposedInDiskConfig(): void
    {
        $this->setDisk([
            'root'          => 'backups',
            'app_key'       => 'disk-key',
            'app_secret'    => 'disk-secret',
            'refresh_token' => 'disk-refresh',
        ]);

        $config = Storage::disk('dropbox')->getConfig();

        $this->assertSame(['driver' => 'dropbox', 'root' => 'backups'], $config);
    }

    public function testRootIsUsedAsPathPrefix(): void
    {
        $this->setDisk(['root' => '/backups/site-1/']);

        $adapter = Storage::disk('dropbox')->getAdapter();
        $prefixer = static::getProtectedProperty($adapter, 'prefixer');

        $this->assertSame('backups/site-1/db.sql', $prefixer->prefixPath('db.sql'));
    }

    public function testWithoutRootPathsAreNotPrefixed(): void
    {
        $this->setDisk([]);

        $prefixer = static::getProtectedProperty(Storage::disk('dropbox')->getAdapter(), 'prefixer');

        $this->assertSame('db.sql', $prefixer->prefixPath('db.sql'));
    }

    public function testFallsBackToPluginConfig(): void
    {
        (new DropboxDiskFactory())->makeTokenProvider($this->app, ['driver' => 'dropbox'])->getToken();

        Http::assertSent(fn (Request $request): bool => $request['client_id'] === 'plugin-key'
            && $request['refresh_token'] === 'plugin-refresh'
            && !isset($request['client_secret']));
    }

    public function testDiskConfigOverridesPluginConfig(): void
    {
        (new DropboxDiskFactory())->makeTokenProvider($this->app, [
            'driver'        => 'dropbox',
            'app_key'       => 'disk-key',
            'app_secret'    => 'disk-secret',
            'refresh_token' => 'disk-refresh',
        ])->getToken();

        Http::assertSent(fn (Request $request): bool => $request['client_id'] === 'disk-key'
            && $request['refresh_token'] === 'disk-refresh'
            && $request['client_secret'] === 'disk-secret');
    }

    public function testEmptyDiskValuesFallBackToPluginConfig(): void
    {
        // e.g. 'app_key' => env('DROPBOX_ARCHIVE_APP_KEY') with the variable not set
        (new DropboxDiskFactory())->makeTokenProvider($this->app, [
            'driver'        => 'dropbox',
            'app_key'       => null,
            'refresh_token' => '',
        ])->getToken();

        Http::assertSent(fn (Request $request): bool => $request['client_id'] === 'plugin-key'
            && $request['refresh_token'] === 'plugin-refresh');
    }

    public function testSeparateDisksUseSeparateTokens(): void
    {
        $factory = new DropboxDiskFactory();

        $first = $factory->makeTokenProvider($this->app, ['app_key' => 'key-1', 'refresh_token' => 'refresh-1']);
        $second = $factory->makeTokenProvider($this->app, ['app_key' => 'key-2', 'refresh_token' => 'refresh-2']);

        $this->assertNotSame($first->cacheKey(), $second->cacheKey());
    }

    public function testMissingAppKeyThrows(): void
    {
        Config::set('numencode.dropboxadapter::app_key', null);
        $this->setDisk([]);

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('DROPBOX_APP_KEY and DROPBOX_REFRESH_TOKEN are required');

        Storage::disk('dropbox');
    }

    public function testMissingRefreshTokenThrows(): void
    {
        Config::set('numencode.dropboxadapter::refresh_token', '');
        $this->setDisk([]);

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('dropboxadapter:setup');

        Storage::disk('dropbox');
    }

    public function testCacheStoreOptionIsUsed(): void
    {
        $this->setDisk(['cache_store' => 'undefined-store']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('undefined-store');

        Storage::disk('dropbox');
    }
}
