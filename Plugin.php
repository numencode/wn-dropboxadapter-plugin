<?php namespace NumenCode\DropboxAdapter;

use System\Classes\PluginBase;
use NumenCode\DropboxAdapter\Console\DropboxSetupCommand;
use NumenCode\DropboxAdapter\Providers\DropboxServiceProvider;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'numencode.dropboxadapter::lang.plugin.name',
            'description' => 'numencode.dropboxadapter::lang.plugin.description',
            'author'      => 'Blaz Orazem',
            'icon'        => 'icon-dropbox',
            'homepage'    => 'https://github.com/numencode/wn-dropboxadapter-plugin',
        ];
    }

    public function boot()
    {
        $this->app->register(DropboxServiceProvider::class);
    }

    public function register()
    {
        $this->registerConsoleCommands();
    }

    protected function registerConsoleCommands()
    {
        $this->registerConsoleCommand('numencode.dropboxadapter_setup', DropboxSetupCommand::class);
    }
}
