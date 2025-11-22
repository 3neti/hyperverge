<?php

namespace LBHurtado\HyperVerge\Data\Validation;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * Result of KYC validation with business rules.
 */
class KYCValidationResultData extends Data
{
    public function __construct(
        /** Whether the KYC result passes validation */
        public bool $valid,
        
        /** Reasons for failure (empty if valid) */
        public array $reasons,
        
        /** The application status from HyperVerge */
        public string $status,
        
        /** When the validation was performed */
        public Carbon $timestamp,
    ) {}
}
