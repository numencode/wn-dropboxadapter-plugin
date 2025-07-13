<?php namespace NumenCode\DropboxAdapter\Tests\Providers;

use PluginTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class DropboxAdapterTest extends PluginTestCase
{
    /**
     * Test Dropbox driver registration and basic usage.
     */
    public function testDropboxDriverInitialization(): void
    {
        // 1. Set plugin config values for the temp_token mode directly
        Config::set('numencode.dropboxadapter::auth_mode', 'temp_token');
        Config::set('numencode.dropboxadapter::temp_token', 'dummy-temp-token-for-initialization');

        // Also ensure environment variables are set for consistency, though Config::get is now primary
        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=temp_token');
        putenv('DROPBOX_TEMP_TOKEN=dummy-temp-token-for-initialization');

        // 2. Clear cache to ensure a clean state
        Cache::forget('dropbox_access_token');

        // 3. Fake HTTP to prevent actual external calls
        Http::fake();

        // 4. Set up the disk configuration (minimal for temp_token mode)
        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
        ]);

        // Retrieve the disk
        $disk = Storage::disk('dropbox');

        // Assertions
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertTrue(method_exists($disk, 'put'));
        $this->assertTrue(method_exists($disk, 'get'));
        $this->assertTrue(method_exists($disk, 'delete'));

        // Assert that no HTTP calls were made
        Http::assertNothingSent();

        // Clean up environment variables after the test
        putenv('DROPBOX_AUTH_MODE');
        putenv('DROPBOX_TEMP_TOKEN');
    }

    /**
     * Test the refresh token flow when the access token is not cached.
     */
    public function testRefreshTokenFlowWithoutCachedToken(): void
    {
        // Clear any existing cache for 'dropbox_access_token'
        Cache::forget('dropbox_access_token');

        // Mock the HTTP call for refreshing the token
        Http::fake([
            'https://api.dropboxapi.com/oauth2/token' => Http::response([
                'access_token' => 'mocked-fresh-access-token',
                'token_type'   => 'bearer',
                'expires_in'   => 14400,
            ], 200),
        ]);

        // Set environment variables for consistency (though Config::set is primary)
        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=refresh_token');
        putenv('DROPBOX_APP_KEY=test_app_key');
        putenv('DROPBOX_APP_SECRET=test_app_secret');
        putenv('DROPBOX_REFRESH_TOKEN=test_refresh_token');

        // Set plugin config for refresh token mode
        Config::set('numencode.dropboxadapter::auth_mode', 'refresh_token');
        Config::set('numencode.dropboxadapter::app_key', 'test_app_key');
        Config::set('numencode.dropboxadapter::app_secret', 'test_app_secret');
        Config::set('numencode.dropboxadapter::refresh_token', 'test_refresh_token');

        // Provide the necessary config for the 'dropbox' disk itself.
        // These values are passed as the $config array into the Storage::extend closure.
        Config::set('filesystems.disks.dropbox', [
            'driver'        => 'dropbox',
            'key'           => Config::get('numencode.dropboxadapter::app_key'),       // Ensure these are passed
            'secret'        => Config::get('numencode.dropboxadapter::app_secret'),    // to the service provider
            'refresh_token' => Config::get('numencode.dropboxadapter::refresh_token'), // as $config
        ]);

        // Attempt to get the disk, which should trigger the refresh
        $disk = Storage::disk('dropbox');

        // Assertions
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);

        // Assert that the HTTP call was made with the correct parameters
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.dropboxapi.com/oauth2/token' &&
                $request->method() === 'POST' &&
                $request['grant_type'] === 'refresh_token' &&
                $request['refresh_token'] === Config::get('numencode.dropboxadapter::refresh_token') &&
                $request['client_id'] === Config::get('numencode.dropboxadapter::app_key') &&
                $request['client_secret'] === Config::get('numencode.dropboxadapter::app_secret');
        });

        // Assert that the access token is now cached
        $this->assertEquals('mocked-fresh-access-token', Cache::get('dropbox_access_token'));

        // Clean up environment variables
        putenv('DROPBOX_AUTH_MODE');
        putenv('DROPBOX_APP_KEY');
        putenv('DROPBOX_APP_SECRET');
        putenv('DROPBOX_REFRESH_TOKEN');
    }

    /**
     * Test the refresh token flow when the access token IS cached.
     */
    public function testRefreshTokenFlowWithCachedToken(): void
    {
        Cache::put('dropbox_access_token', 'cached-access-token', 12600);
        Http::fake();

        // Set plugin config for refresh token mode (even though it won't hit API)
        Config::set('numencode.dropboxadapter::auth_mode', 'refresh_token');
        Config::set('numencode.dropboxadapter::app_key', 'test_app_key');
        Config::set('numencode.dropboxadapter::app_secret', 'test_app_secret');
        Config::set('numencode.dropboxadapter::refresh_token', 'test_refresh_token');

        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=refresh_token');
        putenv('DROPBOX_APP_KEY=test_app_key');
        putenv('DROPBOX_APP_SECRET=test_app_secret');
        putenv('DROPBOX_REFRESH_TOKEN=test_refresh_token');

        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
        ]);

        $disk = Storage::disk('dropbox');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        Http::assertNothingSent();
        $this->assertEquals('cached-access-token', Cache::get('dropbox_access_token'));

        putenv('DROPBOX_AUTH_MODE');
        putenv('DROPBOX_APP_KEY');
        putenv('DROPBOX_APP_SECRET');
        putenv('DROPBOX_REFRESH_TOKEN');
    }

    /**
     * Test using the temporary token mode.
     */
    public function testTemporaryTokenMode(): void
    {
        Http::fake();

        // Set plugin config for temporary token mode
        Config::set('numencode.dropboxadapter::auth_mode', 'temp_token');
        Config::set('numencode.dropboxadapter::temp_token', 'my-super-secret-temp-token');

        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=temp_token');
        putenv('DROPBOX_TEMP_TOKEN=my-super-secret-temp-token');

        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
        ]);

        $disk = Storage::disk('dropbox');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        Http::assertNothingSent();

        putenv('DROPBOX_AUTH_MODE');
        putenv('DROPBOX_TEMP_TOKEN');
    }

    /**
     * Test temporary token mode with missing token.
     */
    public function testTemporaryTokenModeMissingToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DROPBOX_TEMP_TOKEN is required when DROPBOX_AUTH_MODE is set to \'temp_token\'.');

        // Set plugin config for temporary token mode, but omit the token
        Config::set('numencode.dropboxadapter::auth_mode', 'temp_token');
        Config::set('numencode.dropboxadapter::temp_token', null); // Explicitly null it

        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=temp_token');
        putenv('DROPBOX_TEMP_TOKEN'); // Unset or set to empty

        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
        ]);

        Storage::disk('dropbox');

        putenv('DROPBOX_AUTH_MODE');
    }

    /**
     * Test refresh token flow with missing config.
     */
    public function testRefreshTokenFlowMissingConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Dropbox configuration for refresh token flow (DROPBOX_REFRESH_TOKEN, DROPBOX_APP_KEY, or DROPBOX_APP_SECRET).');

        // Set plugin config for refresh token mode, but omit the required values
        Config::set('numencode.dropboxadapter::auth_mode', 'refresh_token');
        Config::set('numencode.dropboxadapter::app_key', null);
        Config::set('numencode.dropboxadapter::app_secret', null);
        Config::set('numencode.dropboxadapter::refresh_token', null);

        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=refresh_token');
        putenv('DROPBOX_APP_KEY');
        putenv('DROPBOX_APP_SECRET');
        putenv('DROPBOX_REFRESH_TOKEN');

        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
        ]);

        Storage::disk('dropbox');

        putenv('DROPBOX_AUTH_MODE');
    }

    /**
     * Test refresh token API failure.
     */
    public function testRefreshTokenApiFailure(): void
    {
        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        Http::fake([
            'https://api.dropboxapi.com/oauth2/token' => Http::response('{"error": "invalid_grant", "error_description": "Refresh token is invalid"}', 400),
        ]);

        // Set plugin config for refresh token mode with invalid values for the mock
        Config::set('numencode.dropboxadapter::auth_mode', 'refresh_token');
        Config::set('numencode.dropboxadapter::app_key', 'test_app_key');
        Config::set('numencode.dropboxadapter::app_secret', 'test_app_secret');
        Config::set('numencode.dropboxadapter::refresh_token', 'invalid_refresh_token');

        $this->app['env'] = 'testing';
        putenv('DROPBOX_AUTH_MODE=refresh_token');
        putenv('DROPBOX_APP_KEY=test_app_key');
        putenv('DROPBOX_APP_SECRET=test_app_secret');
        putenv('DROPBOX_REFRESH_TOKEN=invalid_refresh_token');

        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
        ]);

        Storage::disk('dropbox');

        putenv('DROPBOX_AUTH_MODE');
        putenv('DROPBOX_APP_KEY');
        putenv('DROPBOX_APP_SECRET');
        putenv('DROPBOX_REFRESH_TOKEN');
    }
}
