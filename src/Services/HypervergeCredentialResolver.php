<?php

namespace LBHurtado\HyperVerge\Services;

use App\Models\Campaign;
use App\Models\CampaignSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Contracts\CredentialResolverInterface;
use LBHurtado\HyperVerge\Data\HypervergeCredentials;

/**
 * Resolves HyperVerge credentials with precedence chain.
 * 
 * Precedence order:
 * 1. Campaign-level credentials (from Campaign.config['hyperverge'])
 * 2. User-level credentials (from User.hyperverge_credentials)
 * 3. Environment credentials (from config/hyperverge.php)
 * 
 * This enables per-campaign and per-user credential overrides while
 * maintaining a sensible fallback to environment defaults.
 */
class HypervergeCredentialResolver implements CredentialResolverInterface
{
    /**
     * Resolve credentials based on context model.
     * 
     * @param Model|null $context Campaign, CampaignSubmission, User, or null
     * @return HypervergeCredentials Resolved credentials
     */
    public function resolve(?Model $context = null): HypervergeCredentials
    {
        if ($context === null) {
            return $this->resolveFromEnvironment();
        }

        // Extract the primary models from context
        $campaign = $this->extractCampaign($context);
        $user = $this->extractUser($context);

        // Campaign-level credentials (highest priority)
        if ($campaign && $this->campaignHasCredentials($campaign)) {
            Log::debug('[CredentialResolver] Using campaign-level credentials', [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
            ]);
            return HypervergeCredentials::fromCampaign($campaign);
        }

        // User-level credentials (medium priority)
        if ($user && $this->userHasCredentials($user)) {
            Log::debug('[CredentialResolver] Using user-level credentials', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);
            return HypervergeCredentials::fromUser($user);
        }

        // Environment credentials (lowest priority - fallback)
        return $this->resolveFromEnvironment();
    }

    /**
     * Extract Campaign model from context.
     */
    protected function extractCampaign(Model $context): ?Campaign
    {
        if ($context instanceof Campaign) {
            return $context;
        }

        if ($context instanceof CampaignSubmission) {
            return $context->campaign;
        }

        return null;
    }

    /**
     * Extract User model from context.
     */
    protected function extractUser(Model $context): ?User
    {
        if ($context instanceof User) {
            return $context;
        }

        // Future: Extract user from CampaignSubmission if relation exists
        // if ($context instanceof CampaignSubmission && $context->user) {
        //     return $context->user;
        // }

        return null;
    }

    /**
     * Check if campaign has custom HyperVerge credentials.
     */
    protected function campaignHasCredentials(Campaign $campaign): bool
    {
        $hyperverge = $campaign->config['hyperverge'] ?? null;

        if (empty($hyperverge) || !is_array($hyperverge)) {
            return false;
        }

        // Must have at least app_id and app_key to be considered valid override
        return !empty($hyperverge['app_id']) && !empty($hyperverge['app_key']);
    }

    /**
     * Check if user has custom HyperVerge credentials.
     */
    protected function userHasCredentials(User $user): bool
    {
        $credentials = $user->hyperverge_credentials;

        if (empty($credentials) || !is_array($credentials)) {
            return false;
        }

        // Must have at least app_id and app_key to be considered valid override
        return !empty($credentials['app_id']) && !empty($credentials['app_key']);
    }

    /**
     * Resolve credentials from environment/config.
     */
    protected function resolveFromEnvironment(): HypervergeCredentials
    {
        Log::debug('[CredentialResolver] Using environment credentials');
        return HypervergeCredentials::fromConfig();
    }
}
