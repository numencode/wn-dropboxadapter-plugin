<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Console;

use Illuminate\Http\Client\Factory as HttpFactory;
use NumenCode\DropboxAdapter\Classes\DropboxAuthException;
use NumenCode\DropboxAdapter\Classes\DropboxOAuth;
use Winter\Storm\Console\Command;
use Winter\Storm\Parse\EnvFile;

/**
 * Authorizes a Dropbox app via OAuth2 and writes the resulting credentials to the .env file.
 * Secrets and tokens are never printed.
 */
class DropboxSetupCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dropboxadapter:setup
        {--with-secret : Use the classic flow with the App secret instead of PKCE}
        {--force : Overwrite existing Dropbox credentials in .env without asking}';

    /**
     * @var string
     */
    protected $description = 'Authorize a Dropbox app and write the refresh token to the .env file.';

    /**
     * Environment variables that were used by version 1.x and are no longer read.
     */
    protected const OBSOLETE_ENV_KEYS = ['DROPBOX_AUTH_MODE', 'DROPBOX_TEMP_TOKEN'];

    public function handle(HttpFactory $http): int
    {
        $envPath = $this->laravel->environmentFilePath();

        if (!is_file($envPath) || !is_writable($envPath)) {
            $this->error("The .env file ({$envPath}) does not exist or is not writable.");
            $this->line('Create it first, e.g. with "php artisan winter:env".');

            return self::FAILURE;
        }

        $env = EnvFile::open($envPath);
        $current = $env->getVariables();

        if (!empty($current['DROPBOX_REFRESH_TOKEN']) && !$this->option('force')
            && !$this->confirm('The .env file already contains a DROPBOX_REFRESH_TOKEN. Replace it?')
        ) {
            $this->line('Nothing was changed.');

            return self::FAILURE;
        }

        $useSecret = (bool) $this->option('with-secret');
        $oauth = new DropboxOAuth($http);

        // Step 1: App key (not a secret, so the current value is offered as default)
        $appKey = trim((string) $this->ask('Dropbox App key', $current['DROPBOX_APP_KEY'] ?? null));

        if ($appKey === '') {
            $this->error('The App key is required.');

            return self::FAILURE;
        }

        // Step 2: App secret (classic flow) or PKCE code verifier
        $appSecret = null;
        $codeVerifier = null;

        if ($useSecret) {
            $appSecret = trim((string) $this->secret('Dropbox App secret (input is hidden)'));

            if ($appSecret === '') {
                $this->error('The App secret is required with --with-secret.');

                return self::FAILURE;
            }
        } else {
            $codeVerifier = DropboxOAuth::generateCodeVerifier();
        }

        // Step 3: Authorization in the browser
        $this->newLine();
        $this->line('Open this URL in your browser, allow access and copy the authorization code:');
        $this->comment($oauth->authorizationUrl(
            $appKey,
            $codeVerifier !== null ? DropboxOAuth::codeChallenge($codeVerifier) : null
        ));
        $this->newLine();

        $authCode = trim((string) $this->ask('Authorization code'));

        if ($authCode === '') {
            $this->error('The authorization code is required.');

            return self::FAILURE;
        }

        // Step 4: Exchange the code for tokens
        try {
            $tokens = $oauth->exchangeAuthorizationCode($appKey, $authCode, $appSecret, $codeVerifier);
        } catch (DropboxAuthException $e) {
            $this->error($e->getMessage());
            $this->line('Check the App key' . ($useSecret ? ' and App secret' : '') . ' and request a new authorization code.');

            return self::FAILURE;
        }

        if (empty($tokens['refresh_token']) || !is_string($tokens['refresh_token'])) {
            $this->error('Dropbox did not return a refresh token.');

            return self::FAILURE;
        }

        $this->verifyAccount($oauth, $tokens['access_token']);

        // Step 5: Write credentials to .env
        $values = [
            'DROPBOX_APP_KEY'       => $appKey,
            'DROPBOX_REFRESH_TOKEN' => $tokens['refresh_token'],
        ];

        if ($useSecret) {
            $values['DROPBOX_APP_SECRET'] = $appSecret;
        } elseif (!empty($current['DROPBOX_APP_SECRET'])) {
            // A PKCE refresh token doesn't need the secret – don't keep it on the server.
            $values['DROPBOX_APP_SECRET'] = '';
        }

        $env->set($values);
        $env->write();

        $this->info('✔ Dropbox credentials written to .env: ' . implode(', ', array_keys($values)));

        if (!$useSecret && array_key_exists('DROPBOX_APP_SECRET', $values)) {
            $this->line('DROPBOX_APP_SECRET was cleared – it is not needed with PKCE.');
        }

        $obsolete = array_values(array_intersect(self::OBSOLETE_ENV_KEYS, array_keys($current)));

        if ($obsolete) {
            $this->warn('These variables are no longer used and can be removed from .env: ' . implode(', ', $obsolete));
        }

        if ($this->laravel->configurationIsCached()) {
            $this->warn('The configuration is cached – run "php artisan config:cache" to apply the new credentials.');
        }

        return self::SUCCESS;
    }

    /**
     * Confirm the new token works by fetching the account name (needs no extra scopes).
     */
    protected function verifyAccount(DropboxOAuth $oauth, string $accessToken): void
    {
        try {
            $account = $oauth->currentAccount($accessToken);
            $this->info('✔ Authorized for Dropbox account: ' . ($account['name']['display_name'] ?? 'unknown'));
        } catch (DropboxAuthException $e) {
            $this->warn('Could not verify the Dropbox account: ' . $e->getMessage());
        }
    }
}
