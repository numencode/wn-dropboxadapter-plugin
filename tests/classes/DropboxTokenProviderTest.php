<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Tests\Classes;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NumenCode\DropboxAdapter\Classes\DropboxAuthException;
use NumenCode\DropboxAdapter\Classes\DropboxOAuth;
use NumenCode\DropboxAdapter\Classes\DropboxTokenProvider;
use Spatie\Dropbox\Client as DropboxClient;
use System\Tests\Bootstrap\PluginTestCase;

class DropboxTokenProviderTest extends PluginTestCase
{
    protected CacheRepository $cache;

    public function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->app['cache']->store('array');
        $this->cache->flush();
    }

    protected function provider(string $appKey = 'app-key', string $refreshToken = 'refresh', ?string $secret = null): DropboxTokenProvider
    {
        return new DropboxTokenProvider(
            new DropboxOAuth($this->app->make(HttpFactory::class)),
            $this->cache,
            $this->app['encrypter'],
            $appKey,
            $refreshToken,
            $secret,
        );
    }

    /**
     * Fake the token endpoint so that each refresh returns the next token: token-1, token-2, ...
     */
    protected function fakeTokenEndpoint(int $expiresIn = 14400): void
    {
        $counter = 0;

        Http::fake([DropboxOAuth::TOKEN_URL => function () use (&$counter, $expiresIn) {
            return Http::response(['access_token' => 'token-' . ++$counter, 'expires_in' => $expiresIn]);
        }]);
    }

    protected function clientException(int $status): ClientException
    {
        return new ClientException(
            'Dropbox error',
            new PsrRequest('POST', 'https://api.dropboxapi.com/2/files/list_folder'),
            new PsrResponse($status)
        );
    }

    public function testFetchesTokenOnFirstUse(): void
    {
        $this->fakeTokenEndpoint();

        $this->assertSame('token-1', $this->provider()->getToken());
        Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh'
            && $request['client_id'] === 'app-key'
            && !isset($request['client_secret']));
    }

    public function testSendsSecretWhenConfigured(): void
    {
        $this->fakeTokenEndpoint();

        $this->provider(secret: 'app-secret')->getToken();

        Http::assertSent(fn (Request $request): bool => $request['client_secret'] === 'app-secret');
    }

    public function testTokenIsReusedInMemoryAndFromCache(): void
    {
        $this->fakeTokenEndpoint();

        $provider = $this->provider();
        $provider->getToken();
        $provider->getToken();

        // A new instance (e.g. the next request) uses the cached token
        $this->assertSame('token-1', $this->provider()->getToken());
        Http::assertSentCount(1);
    }

    public function testTokenIsCachedEncryptedWithExpiry(): void
    {
        $this->fakeTokenEndpoint(expiresIn: 14400);

        $provider = $this->provider();
        $provider->getToken();

        $payload = $this->cache->get($provider->cacheKey());
        $this->assertIsString($payload);
        $this->assertStringNotContainsString('token-1', $payload);

        $data = $this->app['encrypter']->decrypt($payload);
        $this->assertSame('token-1', $data['token']);
        // expires_in minus the 5 minute safety margin
        $this->assertEqualsWithDelta(time() + 14400 - 300, $data['expires_at'], 5);
    }

    public function testShortLifetimeIsAtLeastOneMinute(): void
    {
        $this->fakeTokenEndpoint(expiresIn: 100);

        $provider = $this->provider();
        $provider->getToken();

        $data = $this->app['encrypter']->decrypt($this->cache->get($provider->cacheKey()));
        $this->assertEqualsWithDelta(time() + 60, $data['expires_at'], 5);
    }

    public function testCacheKeyDependsOnCredentialsAndHidesThem(): void
    {
        $key = $this->provider('app-key', 'secret-refresh-token')->cacheKey();

        $this->assertStringStartsWith('numencode.dropboxadapter.token.', $key);
        $this->assertStringNotContainsString('secret-refresh-token', $key);
        $this->assertSame($key, $this->provider('app-key', 'secret-refresh-token')->cacheKey());
        $this->assertNotSame($key, $this->provider('other-app', 'secret-refresh-token')->cacheKey());
        $this->assertNotSame($key, $this->provider('app-key', 'other-refresh-token')->cacheKey());
    }

    public function testExpiredCacheEntryIsIgnored(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();

        $this->cache->put($provider->cacheKey(), $this->app['encrypter']->encrypt([
            'token'      => 'stale',
            'expires_at' => time() - 1,
        ]), 3600);

        $this->assertSame('token-1', $provider->getToken());
    }

    public function testUnreadableCacheEntryIsReplaced(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();

        // e.g. encrypted with a previous APP_KEY
        $this->cache->put($provider->cacheKey(), 'not-decryptable', 3600);

        $this->assertSame('token-1', $provider->getToken());
        $this->assertNotSame('not-decryptable', $this->cache->get($provider->cacheKey()));
    }

    public function testTokenIsRenewedWhenItExpiresInLongRunningProcess(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();
        $this->assertSame('token-1', $provider->getToken());

        // Simulate the token lifetime running out while the process is still running
        static::setProtectedProperty($provider, 'expiresAt', time() - 1);
        $this->cache->forget($provider->cacheKey());

        $this->assertSame('token-2', $provider->getToken());
    }

    public function testRefreshOn401FetchesNewToken(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();
        $provider->getToken();

        $this->assertTrue($provider->refresh($this->clientException(401)));
        $this->assertSame('token-2', $provider->getToken());
        $this->assertSame('token-2', $this->provider()->getToken(), 'The refreshed token is cached.');
    }

    public function testOtherClientErrorsAreNotRefreshed(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();
        $provider->getToken();

        $this->assertFalse($provider->refresh($this->clientException(409)));
        $this->assertFalse($provider->refresh($this->clientException(403)));
        $this->assertSame('token-1', $provider->getToken());
        Http::assertSentCount(1);
    }

    public function testForgetClearsMemoryAndCache(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();
        $provider->getToken();

        $provider->forget();

        $this->assertNull($this->cache->get($provider->cacheKey()));
        $this->assertSame('token-2', $provider->getToken());
    }

    public function testFailedRefreshPropagatesException(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->expectException(DropboxAuthException::class);

        $this->provider()->getToken();
    }

    public function testDropboxClientRetriesWithFreshTokenAfter401(): void
    {
        $this->fakeTokenEndpoint();
        $provider = $this->provider();

        // Pretend a token is cached which Dropbox has meanwhile revoked
        $this->cache->put($provider->cacheKey(), $this->app['encrypter']->encrypt([
            'token'      => 'revoked',
            'expires_at' => time() + 3600,
        ]), 3600);

        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new PsrResponse(401, [], '{"error_summary": "expired_access_token/"}'),
            new PsrResponse(200, [], '{"account_id": "dbid:123"}'),
        ]));
        $handler->push(Middleware::history($history));

        $client = new DropboxClient($provider, new GuzzleClient(['handler' => $handler]));

        $this->assertSame(['account_id' => 'dbid:123'], $client->rpcEndpointRequest('users/get_current_account'));
        $this->assertCount(2, $history);
        $this->assertSame('Bearer revoked', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer token-1', $history[1]['request']->getHeaderLine('Authorization'));
    }
}
