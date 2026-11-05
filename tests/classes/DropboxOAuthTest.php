<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Tests\Classes;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NumenCode\DropboxAdapter\Classes\DropboxAuthException;
use NumenCode\DropboxAdapter\Classes\DropboxOAuth;
use System\Tests\Bootstrap\PluginTestCase;

class DropboxOAuthTest extends PluginTestCase
{
    protected function oauth(): DropboxOAuth
    {
        return new DropboxOAuth($this->app->make(HttpFactory::class));
    }

    protected function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    public function testAuthorizationUrlForClassicFlow(): void
    {
        $url = $this->oauth()->authorizationUrl('app key&x=1');

        $this->assertStringStartsWith(DropboxOAuth::AUTHORIZE_URL . '?', $url);
        $this->assertSame([
            'client_id'         => 'app key&x=1',
            'response_type'     => 'code',
            'token_access_type' => 'offline',
        ], $this->queryOf($url));
    }

    public function testAuthorizationUrlForPkceFlow(): void
    {
        $query = $this->queryOf($this->oauth()->authorizationUrl('app-key', 'challenge-value'));

        $this->assertSame('app-key', $query['client_id']);
        $this->assertSame('offline', $query['token_access_type']);
        $this->assertSame('challenge-value', $query['code_challenge']);
        $this->assertSame('S256', $query['code_challenge_method']);
    }

    public function testCodeVerifierIsRandomAndValid(): void
    {
        $verifier = DropboxOAuth::generateCodeVerifier();

        // RFC 7636: 43–128 characters from the unreserved set
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier);
        $this->assertNotSame($verifier, DropboxOAuth::generateCodeVerifier());
    }

    public function testCodeChallengeIsUnpaddedBase64UrlSha256(): void
    {
        $verifier = DropboxOAuth::generateCodeVerifier();
        $challenge = DropboxOAuth::codeChallenge($verifier);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]{43}$/', $challenge);
        $this->assertSame(
            hash('sha256', $verifier, true),
            base64_decode(strtr($challenge, '-_', '+/') . '=')
        );
        $this->assertSame($challenge, DropboxOAuth::codeChallenge($verifier));
    }

    public function testExchangeAuthorizationCodeWithPkce(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response([
            'access_token'  => 'access',
            'refresh_token' => 'refresh',
            'expires_in'    => 14400,
        ])]);

        $tokens = $this->oauth()->exchangeAuthorizationCode('app-key', 'auth-code', null, 'verifier');

        $this->assertSame('refresh', $tokens['refresh_token']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->isForm()
            && $request->data() === [
                'grant_type'    => 'authorization_code',
                'code'          => 'auth-code',
                'client_id'     => 'app-key',
                'code_verifier' => 'verifier',
            ]);
    }

    public function testExchangeAuthorizationCodeWithSecret(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response(['access_token' => 'access'])]);

        $this->oauth()->exchangeAuthorizationCode('app-key', 'auth-code', 'app-secret');

        Http::assertSent(fn (Request $request): bool => $request['client_secret'] === 'app-secret'
            && !isset($request['code_verifier']));
    }

    public function testRefreshAccessTokenOmitsEmptySecret(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response(['access_token' => 'access', 'expires_in' => 14400])]);

        $tokens = $this->oauth()->refreshAccessToken('app-key', 'refresh', '');

        $this->assertSame('access', $tokens['access_token']);
        Http::assertSent(fn (Request $request): bool => $request->data() === [
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'refresh',
            'client_id'     => 'app-key',
        ]);
    }

    public function testRefreshAccessTokenSendsSecret(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response(['access_token' => 'access'])]);

        $this->oauth()->refreshAccessToken('app-key', 'refresh', 'app-secret');

        Http::assertSent(fn (Request $request): bool => $request['client_secret'] === 'app-secret');
    }

    public function testErrorResponseThrowsWithDropboxDescription(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response([
            'error'             => 'invalid_grant',
            'error_description' => 'refresh token is malformed',
        ], 400)]);

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('refresh token is malformed');

        $this->oauth()->refreshAccessToken('app-key', 'bad-token');
    }

    public function testErrorResponseWithoutBodyThrowsWithStatus(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response('', 503)]);

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('HTTP 503');

        $this->oauth()->refreshAccessToken('app-key', 'refresh');
    }

    public function testMissingAccessTokenThrows(): void
    {
        Http::fake([DropboxOAuth::TOKEN_URL => Http::response(['token_type' => 'bearer'])]);

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('did not return an access token');

        $this->oauth()->refreshAccessToken('app-key', 'refresh');
    }

    public function testConnectionErrorThrows(): void
    {
        Http::fake(function (): never {
            throw new ConnectException('Connection timed out', new PsrRequest('POST', DropboxOAuth::TOKEN_URL));
        });

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('Could not connect to the Dropbox OAuth2 endpoint');

        $this->oauth()->refreshAccessToken('app-key', 'refresh');
    }

    public function testCurrentAccount(): void
    {
        Http::fake([DropboxOAuth::CURRENT_ACCOUNT_URL => Http::response([
            'account_id' => 'dbid:123',
            'name'       => ['display_name' => 'Jane Doe'],
        ])]);

        $account = $this->oauth()->currentAccount('access-token');

        $this->assertSame('Jane Doe', $account['name']['display_name']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-token')
            && $request->body() === 'null'
            && $request->hasHeader('Content-Type', 'application/json'));
    }

    public function testCurrentAccountFailureThrows(): void
    {
        Http::fake([DropboxOAuth::CURRENT_ACCOUNT_URL => Http::response(['error' => 'expired_access_token'], 401)]);

        $this->expectException(DropboxAuthException::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->oauth()->currentAccount('expired');
    }
}
