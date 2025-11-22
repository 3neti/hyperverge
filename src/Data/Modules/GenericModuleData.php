<?php

namespace LBHurtado\HyperVerge\Data\Modules;

/**
 * Fallback module representation when we don't have a specific DTO.
 */
class GenericModuleData extends HypervergeModuleData
{
    public static function fromHyperverge(array $module): self
    {
        $details = $module['details'] ?? [];

        return new self(
            module: (string)($module['module'] ?? 'Generic Module'),
            status: (string)($module['status'] ?? ''),
            moduleId: $module['moduleId'] ?? null,
            details: $details,
            raw: $module,
        );
    }
}
