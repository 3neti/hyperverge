<?php

namespace LBHurtado\HyperVerge;

use LBHurtado\HyperVerge\Contracts\CredentialResolverInterface;
use LBHurtado\HyperVerge\Contracts\DocumentStoragePort;
use LBHurtado\HyperVerge\Contracts\TileAllocator;
use LBHurtado\HyperVerge\Contracts\VerificationUrlResolver;
use LBHurtado\HyperVerge\Factories\HypervergeClientFactory;
use LBHurtado\HyperVerge\Services\DefaultTileAllocator;
use LBHurtado\HyperVerge\Services\DefaultVerificationUrlResolver;
use LBHurtado\HyperVerge\Services\HypervergeCredentialResolver;
use LBHurtado\HyperVerge\Services\SpatieDocumentStorage;
use LBHurtado\HyperVerge\Support\HypervergeClient;
use Illuminate\Support\ServiceProvider;

class HypervergeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/hyperverge.php', 'hyperverge');

        // Register credential resolver
        $this->app->singleton(CredentialResolverInterface::class, HypervergeCredentialResolver::class);
        
        // Register client factory
        $this->app->singleton(HypervergeClientFactory::class, function ($app) {
            return new HypervergeClientFactory(
                $app->make(CredentialResolverInterface::class)
            );
        });

        // Register default client (backward compatibility)
        $this->app->singleton(HypervergeClient::class, function ($app) {
            return new HypervergeClient(config('hyperverge'));
        });

        // Register document signing contracts
        $this->app->singleton(TileAllocator::class, DefaultTileAllocator::class);
        $this->app->singleton(VerificationUrlResolver::class, DefaultVerificationUrlResolver::class);
        $this->app->singleton(DocumentStoragePort::class, SpatieDocumentStorage::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/hyperverge.php' => config_path('hyperverge.php'),
        ], 'hyperverge-config');

        // Publish document signing migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/add_document_signing_to_campaigns_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_add_document_signing_to_campaigns_table.php'),
        ], 'hyperverge-migrations');

        $this->loadRoutesFrom(__DIR__ . '/routes/webhooks.php');
    }
}
