<?php namespace NumenCode\DropboxAdapter\Tests\Providers;

use PluginTestCase;
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
        // Provide dummy config for Dropbox
        Config::set('filesystems.disks.dropbox', [
            'driver' => 'dropbox',
            'authorization_token' => 'dummy-token',
        ]);

        // Retrieve the disk
        $disk = Storage::disk('dropbox');

        // Assertions
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertTrue(method_exists($disk, 'put'));
        $this->assertTrue(method_exists($disk, 'get'));
        $this->assertTrue(method_exists($disk, 'delete'));
    }
}
