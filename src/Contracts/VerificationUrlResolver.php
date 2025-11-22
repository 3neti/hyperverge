<?php

namespace LBHurtado\HyperVerge\Contracts;

use Illuminate\Database\Eloquent\Model;

interface VerificationUrlResolver
{
    /**
     * Resolve the verification URL for a signed document.
     *
     * @param Model $model The model that owns the signed document (e.g., CampaignSubmission)
     * @param string|null $transactionId Optional transaction ID for additional context
     * @return string The full verification URL
     */
    public function resolve(Model $model, ?string $transactionId = null): string;
}
