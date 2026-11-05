<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Tests\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NumenCode\DropboxAdapter\Classes\DropboxOAuth;
use NumenCode\DropboxAdapter\Console\DropboxSetupCommand;
use System\Tests\Bootstrap\PluginTestCase;

class DropboxSetupCommandTest extends PluginTestCase
{
    protected const APP_KEY_QUESTION = 'Dropbox App key';
    protected const SECRET_QUESTION = 'Dropbox App secret (input is hidden)';
    protected const CODE_QUESTION = 'Authorization code';
    protected const REPLACE_QUESTION = 'The .env file already contains a DROPBOX_REFRESH_TOKEN. Replace it?';

    protected string $envDir;

    public function setUp(): void
    {
        parent::setUp();

        // PluginTestCase loads the plugin after Artisan is created, so register the command explicitly
        $this->app[Kernel::class]->registerCommand($this->app->make(DropboxSetupCommand::class));

        $this->envDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dropboxadapter-test-' . bin2hex(random_bytes(6));
        mkdir($this->envDir);
        $this->app->useEnvironmentPath($this->envDir);
    }

    public function tearDown(): void
    {
        @unlink($this->envFile());
        @rmdir($this->envDir);

        parent::tearDown();
    }

    protected function envFile(): string
    {
        return $this->envDir . DIRECTORY_SEPARATOR . '.env';
    }

    protected function writeEnv(string $contents): void
    {
        file_put_contents($this->envFile(), $contents);
    }

    /**
     * @return array<string, string>
     */
    protected function readEnv(): array
    {
        $values = [];

        foreach (file($this->envFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[$key] = trim($value, '"');
        }

        return $values;
    }

    protected function fakeDropbox(array $tokenResponse = [], int $tokenStatus = 200, int $accountStatus = 200): void
    {
        Http::fake([
            DropboxOAuth::TOKEN_URL => Http::response($tokenResponse ?: [
                'access_token'  => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in'    => 14400,
            ], $tokenStatus),
            DropboxOAuth::CURRENT_ACCOUNT_URL => Http::response(
                ['account_id' => 'dbid:123', 'name' => ['display_name' => 'Jane Doe']],
                $accountStatus
            ),
        ]);
    }

    public function testPkceSetupWritesCredentialsWithoutPrintingThem(): void
    {
        $this->writeEnv("APP_NAME=\"Winter\"\n# Dropbox\nDROPBOX_APP_KEY=\nOTHER=keep\n");
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsOutputToContain('code_challenge_method=S256')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->expectsOutputToContain('Authorized for Dropbox account: Jane Doe')
            ->expectsOutputToContain('Dropbox credentials written to .env')
            ->doesntExpectOutputToContain('new-refresh-token')
            ->doesntExpectOutputToContain('new-access-token')
            ->assertExitCode(0);

        $env = $this->readEnv();
        $this->assertSame('my-app-key', $env['DROPBOX_APP_KEY']);
        $this->assertSame('new-refresh-token', $env['DROPBOX_REFRESH_TOKEN']);
        $this->assertArrayNotHasKey('DROPBOX_APP_SECRET', $env);
        $this->assertSame('keep', $env['OTHER']);
        $this->assertStringContainsString('# Dropbox', file_get_contents($this->envFile()));

        Http::assertSent(fn (Request $request): bool => $request->url() === DropboxOAuth::TOKEN_URL
            && $request['code'] === 'the-auth-code'
            && $request['client_id'] === 'my-app-key'
            && strlen((string) $request['code_verifier']) >= 43
            && !isset($request['client_secret']));
    }

    public function testPkceSetupClearsExistingAppSecret(): void
    {
        $this->writeEnv("DROPBOX_APP_KEY=old-key\nDROPBOX_APP_SECRET=old-secret\n");
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'old-key')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->expectsOutputToContain('DROPBOX_APP_SECRET was cleared')
            ->doesntExpectOutputToContain('old-secret')
            ->assertExitCode(0);

        $this->assertSame('', $this->readEnv()['DROPBOX_APP_SECRET']);
    }

    public function testSetupWithSecret(): void
    {
        $this->writeEnv("APP_NAME=Winter\n");
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup', ['--with-secret' => true])
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::SECRET_QUESTION, 'my-app-secret')
            ->doesntExpectOutputToContain('code_challenge')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->doesntExpectOutputToContain('my-app-secret')
            ->assertExitCode(0);

        $env = $this->readEnv();
        $this->assertSame('my-app-secret', $env['DROPBOX_APP_SECRET']);
        $this->assertSame('new-refresh-token', $env['DROPBOX_REFRESH_TOKEN']);

        Http::assertSent(fn (Request $request): bool => $request->url() === DropboxOAuth::TOKEN_URL
            && $request['client_secret'] === 'my-app-secret'
            && !isset($request['code_verifier']));
    }

    public function testExistingRefreshTokenIsKeptWhenNotConfirmed(): void
    {
        $original = "DROPBOX_APP_KEY=old-key\nDROPBOX_REFRESH_TOKEN=old-refresh\n";
        $this->writeEnv($original);
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup')
            ->expectsConfirmation(self::REPLACE_QUESTION, 'no')
            ->expectsOutput('Nothing was changed.')
            ->assertExitCode(1);

        $this->assertSame($original, file_get_contents($this->envFile()));
        Http::assertNothingSent();
    }

    public function testExistingRefreshTokenIsReplacedWhenConfirmed(): void
    {
        $this->writeEnv("DROPBOX_APP_KEY=old-key\nDROPBOX_REFRESH_TOKEN=old-refresh\n");
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup')
            ->expectsConfirmation(self::REPLACE_QUESTION, 'yes')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'old-key')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->assertExitCode(0);

        $this->assertSame('new-refresh-token', $this->readEnv()['DROPBOX_REFRESH_TOKEN']);
    }

    public function testForceSkipsConfirmation(): void
    {
        $this->writeEnv("DROPBOX_APP_KEY=old-key\nDROPBOX_REFRESH_TOKEN=old-refresh\n");
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup', ['--force' => true])
            ->expectsQuestion(self::APP_KEY_QUESTION, 'old-key')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->assertExitCode(0);

        $this->assertSame('new-refresh-token', $this->readEnv()['DROPBOX_REFRESH_TOKEN']);
    }

    public function testWarnsAboutObsoleteVariables(): void
    {
        $this->writeEnv("DROPBOX_AUTH_MODE=temp_token\nDROPBOX_TEMP_TOKEN=abc\n");
        $this->fakeDropbox();

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->expectsOutputToContain('no longer used and can be removed from .env: DROPBOX_AUTH_MODE, DROPBOX_TEMP_TOKEN')
            ->assertExitCode(0);
    }

    public function testFailsWithoutEnvFile(): void
    {
        $this->artisan('dropboxadapter:setup')
            ->expectsOutputToContain('does not exist or is not writable')
            ->assertExitCode(1);
    }

    public function testFailsWithoutAppKey(): void
    {
        $this->writeEnv("APP_NAME=Winter\n");

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, '')
            ->expectsOutputToContain('The App key is required.')
            ->assertExitCode(1);
    }

    public function testFailsWithoutAppSecretInSecretMode(): void
    {
        $this->writeEnv("APP_NAME=Winter\n");

        $this->artisan('dropboxadapter:setup', ['--with-secret' => true])
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::SECRET_QUESTION, '')
            ->expectsOutputToContain('The App secret is required with --with-secret.')
            ->assertExitCode(1);
    }

    public function testFailsWithoutAuthorizationCode(): void
    {
        $this->writeEnv("APP_NAME=Winter\n");

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::CODE_QUESTION, '')
            ->expectsOutputToContain('The authorization code is required.')
            ->assertExitCode(1);
    }

    public function testDropboxErrorLeavesEnvUnchanged(): void
    {
        $original = "APP_NAME=Winter\n";
        $this->writeEnv($original);
        $this->fakeDropbox(['error' => 'invalid_grant', 'error_description' => 'code doesn\'t exist or has expired'], 400);

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::CODE_QUESTION, 'expired-code')
            ->expectsOutputToContain('code doesn\'t exist or has expired')
            ->assertExitCode(1);

        $this->assertSame($original, file_get_contents($this->envFile()));
    }

    public function testMissingRefreshTokenInResponseFails(): void
    {
        $original = "APP_NAME=Winter\n";
        $this->writeEnv($original);
        $this->fakeDropbox(['access_token' => 'new-access-token', 'expires_in' => 14400]);

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->expectsOutputToContain('Dropbox did not return a refresh token.')
            ->assertExitCode(1);

        $this->assertSame($original, file_get_contents($this->envFile()));
    }

    public function testFailedAccountVerificationOnlyWarns(): void
    {
        $this->writeEnv("APP_NAME=Winter\n");
        $this->fakeDropbox(accountStatus: 500);

        $this->artisan('dropboxadapter:setup')
            ->expectsQuestion(self::APP_KEY_QUESTION, 'my-app-key')
            ->expectsQuestion(self::CODE_QUESTION, 'the-auth-code')
            ->expectsOutputToContain('Could not verify the Dropbox account')
            ->assertExitCode(0);

        $this->assertSame('new-refresh-token', $this->readEnv()['DROPBOX_REFRESH_TOKEN']);
    }
}
