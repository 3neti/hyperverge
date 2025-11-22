<?php

namespace LBHurtado\HyperVerge\Tests;

use Intervention\Image\ImageServiceProvider;
use LBHurtado\HyperVerge\HypervergeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            HypervergeServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ImageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup HyperVerge config
        $app['config']->set('hyperverge.base_url', 'https://test.hyperverge.co/v1');
        $app['config']->set('hyperverge.app_id', 'test_app_id');
        $app['config']->set('hyperverge.app_key', 'test_app_key');
        $app['config']->set('hyperverge.timeout', 30);

        // Setup filesystems
        $app['config']->set('filesystems.default', 'public');
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => sys_get_temp_dir() . '/hyperverge-test',
            'visibility' => 'public',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load our package test migrations
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }
}
