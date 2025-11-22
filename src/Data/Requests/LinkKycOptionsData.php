<?php

namespace LBHurtado\HyperVerge\Data\Requests;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Options DTO for Link KYC onboarding link generation.
 * 
 * Based on HyperVerge Link KYC API documentation:
 * https://documentation.hyperverge.co/onboard-links/
 * 
 * @example
 * // Basic usage
 * $options = LinkKycOptionsData::from([
 *     'validateWorkflowInputs' => 'yes',
 *     'redirectTime' => '10',
 * ]);
 * 
 * // With authentication on resume
 * $options = LinkKycOptionsData::from([
 *     'authenticateOnResume' => 'yes',
 *     'mobileNumber' => '+639171234567',
 * ]);
 * 
 * // With email authentication
 * $options = LinkKycOptionsData::from([
 *     'authenticateOnResume' => 'yes',
 *     'email' => 'user@example.com',
 * ]);
 */
class LinkKycOptionsData extends Data
{
    public function __construct(
        /** Whether to validate workflow inputs before creating link */
        public string|Optional $validateWorkflowInputs = 'yes',
        
        /** Whether to allow empty workflow inputs */
        public string|Optional $allowEmptyWorkflowInputs = 'no',
        
        /** Whether to force create a new link even if one exists */
        public string|Optional $forceCreateLink = 'no',
        
        /** Time in seconds before redirecting user after completion */
        public string|Optional $redirectTime = '5',
        
        /** Whether to authenticate user when resuming session */
        public string|Optional $authenticateOnResume = 'no',
        
        /** Mobile number for authentication (required if authenticateOnResume is 'yes') */
        public string|Optional|null $mobileNumber = null,
        
        /** Email for authentication (required if authenticateOnResume is 'yes') */
        public string|Optional|null $email = null,
    ) {
    }

    /**
     * Create with authentication via mobile number.
     */
    public static function withMobileAuth(
        string $mobileNumber,
        string $redirectTime = '5',
        bool $validateWorkflowInputs = true,
    ): self {
        return new self(
            validateWorkflowInputs: $validateWorkflowInputs ? 'yes' : 'no',
            redirectTime: $redirectTime,
            authenticateOnResume: 'yes',
            mobileNumber: $mobileNumber,
        );
    }

    /**
     * Create with authentication via email.
     */
    public static function withEmailAuth(
        string $email,
        string $redirectTime = '5',
        bool $validateWorkflowInputs = true,
    ): self {
        return new self(
            validateWorkflowInputs: $validateWorkflowInputs ? 'yes' : 'no',
            redirectTime: $redirectTime,
            authenticateOnResume: 'yes',
            email: $email,
        );
    }

    /**
     * Create default options (no authentication).
     */
    public static function defaults(
        string $redirectTime = '5',
        bool $validateWorkflowInputs = true,
        bool $allowEmptyWorkflowInputs = false,
        bool $forceCreateLink = false,
    ): self {
        return new self(
            validateWorkflowInputs: $validateWorkflowInputs ? 'yes' : 'no',
            allowEmptyWorkflowInputs: $allowEmptyWorkflowInputs ? 'yes' : 'no',
            forceCreateLink: $forceCreateLink ? 'yes' : 'no',
            redirectTime: $redirectTime,
        );
    }

    /**
     * Convert to array for API payload, excluding Optional values.
     */
    public function toPayload(): array
    {
        return collect($this->toArray())
            ->reject(fn ($value) => $value instanceof Optional)
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }
}
