<?php

namespace LBHurtado\HyperVerge\Factories;

use LBHurtado\HyperVerge\Data\Modules\GenericModuleData;
use LBHurtado\HyperVerge\Data\Modules\HypervergeModuleData;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;
use LBHurtado\HyperVerge\Data\Modules\SelfieValidationModuleData;

/**
 * Factory that maps HyperVerge module type to a specific DTO.
 * Uses the 'module' field from HyperVerge API response.
 */
class ModuleFactory
{
    public static function make(array $module): HypervergeModuleData
    {
        $moduleType = $module['module'] ?? '';

        return match ($moduleType) {
            'ID Card Validation front',
            'ID Card Validation back',
            'ID Card Validation' => IdCardModuleData::fromHyperverge($module),

            'Selfie Validation' => SelfieValidationModuleData::fromHyperverge($module),

            default => GenericModuleData::fromHyperverge($module),
        };
    }
}
