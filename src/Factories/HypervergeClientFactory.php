<?php

namespace LBHurtado\HyperVerge\Factories;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\HyperVerge\Contracts\CredentialResolverInterface;
use LBHurtado\HyperVerge\Data\HypervergeCredentials;
use LBHurtado\HyperVerge\Support\HypervergeClient;

/**
 * Factory for creating HypervergeClient instances with resolved credentials.
 * 
 * This factory integrates with the credential resolver to create clients
 * that automatically use the appropriate credentials based on context.
 */
class HypervergeClientFactory
{
    public function __construct(
        protected CredentialResolverInterface $resolver
    ) {
    }

    /**
     * Create a HypervergeClient with credentials resolved from context.
     * 
     * @param Model|null $context Campaign, CampaignSubmission, User, or null
     * @return HypervergeClient Configured client instance
     */
    public function make(?Model $context = null): HypervergeClient
    {
        $credentials = $this->resolver->resolve($context);
        return $this->makeFromCredentials($credentials);
    }

    /**
     * Create a HypervergeClient from explicit credentials.
     * 
     * @param HypervergeCredentials $credentials The credentials to use
     * @return HypervergeClient Configured client instance
     */
    public function makeFromCredentials(HypervergeCredentials $credentials): HypervergeClient
    {
        $config = [
            'app_id' => $credentials->appId,
            'app_key' => $credentials->appKey,
            'base_url' => $credentials->baseUrl ?? config('hyperverge.base_url'),
            'timeout' => config('hyperverge.timeout', 30),
        ];

        return new HypervergeClient($config);
    }

    /**
     * Create a client from environment config (backward compatibility).
     * 
     * @return HypervergeClient Configured client instance
     */
    public function makeDefault(): HypervergeClient
    {
        return $this->makeFromCredentials(HypervergeCredentials::fromConfig());
    }
}
