<?php

namespace LBHurtado\HyperVerge\Data\Modules;

use Spatie\LaravelData\Data;

/**
 * Base class for all HyperVerge modules inside a KYC result.
 * Based on actual HyperVerge API response structure.
 */
abstract class HypervergeModuleData extends Data
{
    public function __construct(
        public string $module,      // The module type/name from 'module' field
        public string $status,      // Module status (auto-approved, etc.)
        public ?string $moduleId,   // Module identifier
        public array $details,      // Extracted details from report
        public array $raw,          // Full raw module data
    ) {
    }
}
