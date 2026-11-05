<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter;

use Illuminate\Support\Facades\Storage;
use NumenCode\DropboxAdapter\Classes\DropboxDiskFactory;
use NumenCode\DropboxAdapter\Console\DropboxSetupCommand;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'numencode.dropboxadapter::lang.plugin.name',
            'description' => 'numencode.dropboxadapter::lang.plugin.description',
            'author'      => 'Blaz Orazem',
            'icon'        => 'icon-dropbox',
            'homepage'    => 'https://www.numencode.com',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('numencode.dropboxadapter.setup', DropboxSetupCommand::class);
    }

    public function boot(): void
    {
        Storage::extend('dropbox', (new DropboxDiskFactory())(...));
    }
}
