<?php

namespace LBHurtado\HyperVerge\Data\Modules;

/**
 * Represents a "Selfie Validation" module.
 * Additional fields: imageUrl
 */
class SelfieValidationModuleData extends HypervergeModuleData
{
    public function __construct(
        string $module,
        string $status,
        ?string $moduleId,
        array $details,
        array $raw,
        public ?string $imageUrl = null,
    ) {
        parent::__construct($module, $status, $moduleId, $details, $raw);
    }

    public static function fromHyperverge(array $module): self
    {
        $details = $module['details'] ?? [];

        return new self(
            module: (string)($module['module'] ?? 'Selfie Validation'),
            status: (string)($module['status'] ?? ''),
            moduleId: $module['moduleId'] ?? null,
            details: $details,
            raw: $module,
            imageUrl: $module['imageUrl'] ?? null,
        );
    }

    public function selfieUrl(): ?string
    {
        return $this->details['selfieUrl'] ?? null;
    }

    public function livenessScore(): ?float
    {
        return isset($this->details['livenessScore'])
            ? (float)$this->details['livenessScore']
            : null;
    }
}
