<?php

namespace LBHurtado\HyperVerge\Data;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Data Transfer Object for HyperVerge API credentials.
 * 
 * Provides type-safe credential handling with factory methods
 * for different credential sources (config, campaign, user).
 */
class HypervergeCredentials
{
    public function __construct(
        public readonly string $appId,
        public readonly string $appKey,
        public readonly string $workflowId,
        public readonly ?string $baseUrl = null,
        public readonly ?string $source = null,
    ) {
    }

    /**
     * Create credentials from environment/config.
     */
    public static function fromConfig(): self
    {
        return new self(
            appId: config('hyperverge.app_id'),
            appKey: config('hyperverge.app_key'),
            workflowId: config('hyperverge.url_workflow', 'onboarding'),
            baseUrl: config('hyperverge.base_url'),
            source: 'config',
        );
    }

    /**
     * Create credentials from a Campaign model.
     */
    public static function fromCampaign(Campaign $campaign): self
    {
        $hyperverge = $campaign->config['hyperverge'] ?? [];

        return new self(
            appId: $hyperverge['app_id'] ?? config('hyperverge.app_id'),
            appKey: $hyperverge['app_key'] ?? config('hyperverge.app_key'),
            workflowId: $hyperverge['workflow_id'] ?? $campaign->workflow_id ?? config('hyperverge.url_workflow', 'onboarding'),
            baseUrl: $hyperverge['base_url'] ?? config('hyperverge.base_url'),
            source: 'campaign:' . $campaign->id,
        );
    }

    /**
     * Create credentials from a User model.
     */
    public static function fromUser(User $user): self
    {
        $credentials = $user->hyperverge_credentials ?? [];

        return new self(
            appId: $credentials['app_id'] ?? config('hyperverge.app_id'),
            appKey: $credentials['app_key'] ?? config('hyperverge.app_key'),
            workflowId: $credentials['workflow_id'] ?? config('hyperverge.url_workflow', 'onboarding'),
            baseUrl: $credentials['base_url'] ?? config('hyperverge.base_url'),
            source: 'user:' . $user->id,
        );
    }

    /**
     * Create credentials from any supported model.
     */
    public static function fromModel(Model $model): self
    {
        return match (true) {
            $model instanceof Campaign => self::fromCampaign($model),
            $model instanceof User => self::fromUser($model),
            default => self::fromConfig(),
        };
    }

    /**
     * Convert to array for HypervergeClient.
     */
    public function toArray(): array
    {
        return [
            'app_id' => $this->appId,
            'app_key' => $this->appKey,
            'workflow_id' => $this->workflowId,
            'base_url' => $this->baseUrl,
        ];
    }

    /**
     * Check if credentials are complete.
     */
    public function isValid(): bool
    {
        return !empty($this->appId) 
            && !empty($this->appKey) 
            && !empty($this->workflowId);
    }

    /**
     * Get debug-safe representation (masks sensitive data).
     */
    public function toDebugArray(): array
    {
        return [
            'app_id' => $this->maskSensitive($this->appId),
            'app_key' => $this->maskSensitive($this->appKey),
            'workflow_id' => $this->workflowId,
            'base_url' => $this->baseUrl,
            'source' => $this->source,
        ];
    }

    private function maskSensitive(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }
}
