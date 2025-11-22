<?php

namespace LBHurtado\HyperVerge\Contracts;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\HyperVerge\Data\HypervergeCredentials;

/**
 * Interface for resolving HyperVerge credentials from various sources.
 * 
 * Implementations should handle precedence logic for credential resolution.
 */
interface CredentialResolverInterface
{
    /**
     * Resolve credentials based on the provided context.
     * 
     * @param Model|null $context The model context (Campaign, CampaignSubmission, User, etc.)
     * @return HypervergeCredentials The resolved credentials
     */
    public function resolve(?Model $context = null): HypervergeCredentials;
}
