<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Classes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Client for the Dropbox OAuth2 endpoints: authorization URL, code exchange and token refresh.
 *
 * Supports both the classic flow (App key + App secret) and PKCE (App key only),
 * see https://developers.dropbox.com/oauth-guide
 */
class DropboxOAuth
{
    public const AUTHORIZE_URL = 'https://www.dropbox.com/oauth2/authorize';

    public const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';

    public const CURRENT_ACCOUNT_URL = 'https://api.dropboxapi.com/2/users/get_current_account';

    public function __construct(
        protected HttpFactory $http,
        protected int $timeout = 30,
    ) {
    }

    /**
     * Build the URL the user opens in the browser to authorize the app.
     * Pass a code challenge to use the PKCE flow.
     */
    public function authorizationUrl(string $appKey, ?string $codeChallenge = null): string
    {
        $query = [
            'client_id'         => $appKey,
            'response_type'     => 'code',
            'token_access_type' => 'offline',
        ];

        if ($codeChallenge !== null) {
            $query['code_challenge'] = $codeChallenge;
            $query['code_challenge_method'] = 'S256';
        }

        return self::AUTHORIZE_URL . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Generate a random PKCE code verifier (86 characters, within the allowed 43–128).
     */
    public static function generateCodeVerifier(): string
    {
        return self::base64UrlEncode(random_bytes(64));
    }

    /**
     * Derive the S256 PKCE code challenge from a code verifier.
     */
    public static function codeChallenge(string $codeVerifier): string
    {
        return self::base64UrlEncode(hash('sha256', $codeVerifier, true));
    }

    /**
     * Exchange an authorization code for an access token and a refresh token.
     *
     * @return array<string, mixed> Dropbox token response (access_token, expires_in, refresh_token, ...)
     *
     * @throws DropboxAuthException
     */
    public function exchangeAuthorizationCode(
        string $appKey,
        string $code,
        ?string $appSecret = null,
        ?string $codeVerifier = null,
    ): array {
        return $this->requestToken([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $appKey,
            'client_secret' => $appSecret,
            'code_verifier' => $codeVerifier,
        ]);
    }

    /**
     * Obtain a new short-lived access token using a refresh token.
     * The App secret is only needed for refresh tokens issued by the classic (non-PKCE) flow.
     *
     * @return array<string, mixed> Dropbox token response (access_token, expires_in, ...)
     *
     * @throws DropboxAuthException
     */
    public function refreshAccessToken(string $appKey, string $refreshToken, ?string $appSecret = null): array
    {
        return $this->requestToken([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $appKey,
            'client_secret' => $appSecret,
        ]);
    }

    /**
     * Fetch the account the access token belongs to (requires only the always-granted account_info.read scope).
     *
     * @return array<string, mixed> Dropbox account (account_id, name.display_name, email, ...)
     *
     * @throws DropboxAuthException
     */
    public function currentAccount(string $accessToken): array
    {
        try {
            $response = $this->http
                ->withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(min(10, $this->timeout))
                ->timeout($this->timeout)
                ->withBody('null', 'application/json')
                ->post(self::CURRENT_ACCOUNT_URL);
        } catch (ConnectionException $e) {
            throw new DropboxAuthException('Could not connect to the Dropbox API: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed() || !is_array($response->json())) {
            throw new DropboxAuthException('Dropbox rejected the account request: HTTP ' . $response->status());
        }

        return $response->json();
    }

    /**
     * @param array<string, string|null> $payload
     * @return array<string, mixed>
     *
     * @throws DropboxAuthException
     */
    protected function requestToken(array $payload): array
    {
        $payload = array_filter($payload, fn (?string $value): bool => $value !== null && $value !== '');

        try {
            $response = $this->http
                ->asForm()
                ->acceptJson()
                ->connectTimeout(min(10, $this->timeout))
                ->timeout($this->timeout)
                ->post(self::TOKEN_URL, $payload);
        } catch (ConnectionException $e) {
            throw new DropboxAuthException('Could not connect to the Dropbox OAuth2 endpoint: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $error = $response->json('error_description') ?: $response->json('error') ?: 'HTTP ' . $response->status();

            throw new DropboxAuthException(sprintf(
                'Dropbox rejected the token request: %s',
                is_string($error) ? $error : json_encode($error)
            ));
        }

        $data = $response->json();

        if (!is_array($data) || empty($data['access_token']) || !is_string($data['access_token'])) {
            throw new DropboxAuthException('Dropbox did not return an access token.');
        }

        return $data;
    }

    protected static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
