<?php

namespace NumenCode\DropboxAdapter\Console;

use Winter\Storm\Console\Command;
use Illuminate\Support\Facades\Http;

class DropboxSetupCommand extends Command
{
    protected $signature = 'dropboxadapter:setup';

    protected $description = 'Performs the initial setup to get a Dropbox refresh token.';

    public function handle()
    {
        $this->info("--- Dropbox API Initial Setup ---\n");
        $this->line("This command will guide you through getting your permanent refresh token.");

        // --- Step 1: Get App Key and construct the authorization URL ---
        $appKey = $this->ask('First, enter your Dropbox App Key');

        if (!$appKey) {
            $this->error('App Key is required. Aborting.');
            return;
        }

        $authUrl = 'https://www.dropbox.com/oauth2/authorize?client_id=' . $appKey . '&response_type=code&token_access_type=offline';

        $this->line("\nGreat. Now, open the following URL in your browser, authorize the app, and then copy the 'code' you receive after being redirected:");
        $this->comment($authUrl); // Display the URL in a distinct color

        // --- Step 2: Get the temporary code and the App Secret from the user ---
        $authCode = $this->ask("\nPaste the 'authorization code' here");
        $appSecret = $this->secret('Enter your Dropbox App Secret (input will be hidden)');

        if (!$authCode || !$appSecret) {
            $this->error('Authorization Code and App Secret are required. Aborting.');
            return;
        }

        $this->line("\nAttempting to exchange the authorization code for a refresh token...");

        // --- Step 3: Make the API call to Dropbox using Laravel's HTTP Client ---
        $response = Http::asForm()->post('https://api.dropbox.com/oauth2/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $authCode,
            'client_id'     => $appKey,
            'client_secret' => $appSecret,
        ]);

        // --- Step 4: Process the response ---
        if ($response->failed()) {
            $this->error("\n[API Error] Failed to get the refresh token.");
            $this->line("Dropbox said: " . $response->body());
            $this->line("\nPlease double-check your credentials and try again.");
            return;
        }

        $refreshToken = $response->json('refresh_token');

        $this->info("\n✔ Success! Your refresh token has been generated.");
        $this->line("\nCopy the token below and add it to your .env file as DROPBOX_REFRESH_TOKEN:");

        // Display the token in a prominent block
        $this->line('+----------------------------------------------------------------+');
        $this->line('| ');
        $this->line('|   ' . $refreshToken);
        $this->line('| ');
        $this->line('+----------------------------------------------------------------+');

        $this->line("\nAlso, make sure your .env file contains your App Key and Secret:\n");
        $this->comment("DROPBOX_APP_KEY=" . $appKey);
        $this->comment("DROPBOX_APP_SECRET=" . $appSecret);
        $this->comment("DROPBOX_REFRESH_TOKEN=" . $refreshToken);
    }
}
