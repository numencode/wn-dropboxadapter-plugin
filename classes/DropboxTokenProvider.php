<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Classes;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Spatie\Dropbox\RefreshableTokenProvider;

/**
 * Supplies short-lived Dropbox access tokens obtained from a long-lived refresh token.
 *
 * - Tokens are cached (encrypted) until shortly before they expire, so they are shared between requests.
 * - The cache key is derived from the credentials, so different apps/accounts never share a token.
 * - Long-running processes (queue workers, large syncs) fetch a new token before the old one expires,
 *   and the Dropbox client retries once with a fresh token when a request fails with 401.
 */
class DropboxTokenProvider implements RefreshableTokenProvider
{
    /**
     * Seconds before the real expiry at which a token is treated as expired.
     */
    protected const EXPIRY_MARGIN = 300;

    protected ?string $token = null;

    protected int $expiresAt = 0;

    public function __construct(
        protected DropboxOAuth $oauth,
        protected CacheRepository $cache,
        protected Encrypter $encrypter,
        protected string $appKey,
        protected string $refreshToken,
        protected ?string $appSecret = null,
    ) {
    }

    public function getToken(): string
    {
        if ($this->token !== null && $this->expiresAt > time()) {
            return $this->token;
        }

        if (!$this->loadFromCache()) {
            $this->fetchToken();
        }

        return $this->token;
    }

    /**
     * Called by the Dropbox client when a request fails. Only an expired/invalid token (401) is refreshed.
     */
    public function refresh(ClientException $exception): bool
    {
        if ($exception->getResponse()->getStatusCode() !== 401) {
            return false;
        }

        $this->forget();
        $this->fetchToken();

        return true;
    }

    /**
     * Drop the token from memory and from the cache.
     */
    public function forget(): void
    {
        $this->token = null;
        $this->expiresAt = 0;
        $this->cache->forget($this->cacheKey());
    }

    public function cacheKey(): string
    {
        return 'numencode.dropboxadapter.token.' . hash('sha256', $this->appKey . "\0" . $this->refreshToken);
    }

    protected function loadFromCache(): bool
    {
        $payload = $this->cache->get($this->cacheKey());

        if (!is_string($payload)) {
            return false;
        }

        try {
            $data = $this->encrypter->decrypt($payload);
        } catch (DecryptException) {
            // e.g. APP_KEY was rotated – discard the unreadable entry
            $this->cache->forget($this->cacheKey());

            return false;
        }

        if (!is_array($data) || empty($data['token']) || ($data['expires_at'] ?? 0) <= time()) {
            return false;
        }

        $this->token = $data['token'];
        $this->expiresAt = (int) $data['expires_at'];

        return true;
    }

    protected function fetchToken(): void
    {
        $data = $this->oauth->refreshAccessToken($this->appKey, $this->refreshToken, $this->appSecret);

        // Dropbox access tokens currently live for 4 hours; fall back to that if expires_in is missing.
        $lifetime = max(60, (int) ($data['expires_in'] ?? 14400) - self::EXPIRY_MARGIN);

        $this->token = $data['access_token'];
        $this->expiresAt = time() + $lifetime;

        $this->cache->put(
            $this->cacheKey(),
            $this->encrypter->encrypt(['token' => $this->token, 'expires_at' => $this->expiresAt]),
            $lifetime
        );
    }
}
