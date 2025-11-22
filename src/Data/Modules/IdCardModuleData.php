<?php

namespace LBHurtado\HyperVerge\Data\Modules;

/**
 * Represents an "ID Card Validation" module.
 * Additional fields: countrySelected, documentSelected, imageUrl, croppedImageUrl, attempts
 */
class IdCardModuleData extends HypervergeModuleData
{
    public function __construct(
        string $module,
        string $status,
        ?string $moduleId,
        array $details,
        array $raw,
        public ?string $countrySelected = null,
        public ?string $documentSelected = null,
        public ?string $imageUrl = null,
        public ?string $croppedImageUrl = null,
        public ?int $attempts = null,
    ) {
        parent::__construct($module, $status, $moduleId, $details, $raw);
    }

    public static function fromHyperverge(array $module): self
    {
        $details = $module['details'] ?? [];
        
        // If details are empty, extract and transform from nested apiResponse structure
        if (empty($details) && isset($module['apiResponse']['result']['details'][0]['fieldsExtracted'])) {
            $fieldsExtracted = $module['apiResponse']['result']['details'][0]['fieldsExtracted'];
            
            // Extract and transform data
            $details = [
                'fullName' => self::transformFullName($fieldsExtracted['fullName']['value'] ?? null),
                'dateOfBirth' => self::transformBirthDate($fieldsExtracted['dateOfBirth']['value'] ?? null),
                'email' => self::transformEmail($fieldsExtracted['email']['value'] ?? null),
                'mobile' => self::transformMobile(
                    $fieldsExtracted['mobile']['value'] ?? $fieldsExtracted['mobileNumber']['value'] ?? null
                ),
                'address' => self::transformAddress($fieldsExtracted['address']['value'] ?? null),
                'idType' => self::transformIdType(
                    $fieldsExtracted, 
                    $module['countrySelected'] ?? null, 
                    $module['documentSelected'] ?? null
                ),
                'idNumber' => $fieldsExtracted['idNumber']['value'] ?? null,
            ];
        }

        return new self(
            module: (string)($module['module'] ?? 'ID Card Validation'),
            status: (string)($module['status'] ?? ''),
            moduleId: $module['moduleId'] ?? null,
            details: $details,
            raw: $module,
            countrySelected: $module['countrySelected'] ?? null,
            documentSelected: $module['documentSelected'] ?? null,
            imageUrl: $module['imageUrl'] ?? null,
            croppedImageUrl: $module['croppedImageUrl'] ?? null,
            attempts: isset($module['attempts']) ? (int)$module['attempts'] : null,
        );
    }

    public function idNumber(): ?string
    {
        return $this->details['idNumber'] ?? null;
    }

    public function fullName(): ?string
    {
        return $this->details['fullName'] ?? null;
    }

    public function country(): ?string
    {
        return $this->details['countrySelected'] ?? null;
    }

    public function idType(): ?string
    {
        return $this->details['idType'] ?? null;
    }

    /**
     * Transform full name to title case.
     */
    protected static function transformFullName(?string $value): ?string
    {
        if (!$value || empty($value)) {
            return null;
        }
        return trim(ucwords(strtolower($value)));
    }

    /**
     * Transform birth date from d-m-Y to Y-m-d format.
     */
    protected static function transformBirthDate(?string $value): ?string
    {
        if (!$value || empty($value)) {
            return null;
        }
        
        // HyperVerge format: "21-04-1970" (d-m-Y)
        try {
            $date = \Carbon\Carbon::createFromFormat('d-m-Y', $value);
            return $date ? $date->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Transform email to lowercase.
     */
    protected static function transformEmail(?string $value): ?string
    {
        if (!$value || empty($value)) {
            return null;
        }
        return strtolower(trim($value));
    }

    /**
     * Transform mobile to E.164 format.
     */
    protected static function transformMobile(?string $value): ?string
    {
        if (!$value || empty($value)) {
            return null;
        }
        
        // Remove all non-numeric characters except +
        $mobile = preg_replace('/[^0-9+]/', '', $value);
        
        // If starts with 0, assume Philippines and convert to +63
        if (str_starts_with($mobile, '0')) {
            $mobile = '+63' . substr($mobile, 1);
        }
        
        // Ensure it has country code
        if (!str_starts_with($mobile, '+')) {
            $mobile = '+' . $mobile;
        }
        
        return $mobile;
    }

    /**
     * Transform address to title case.
     */
    protected static function transformAddress(?string $value): ?string
    {
        if (!$value || empty($value)) {
            return null;
        }
        return trim(ucwords(strtolower($value)));
    }

    /**
     * Transform/format ID type.
     */
    protected static function transformIdType(array $fieldsExtracted, ?string $country, ?string $document): ?string
    {
        // Try to get from fieldsExtracted first
        $idType = $fieldsExtracted['type']['value'] ?? null;
        
        if ($idType && !empty($idType)) {
            return $idType;
        }
        
        // Fallback: construct from country + document
        if ($country && $document) {
            $countryMap = [
                'phl' => 'PHL',
                'ph' => 'PHL',
            ];
            $documentMap = [
                'dl' => "Driver's License",
                'passport' => 'Passport',
                'umid' => 'UMID',
                'sss' => 'SSS',
                'philhealth' => 'PhilHealth',
                'tin' => 'TIN',
                'postal' => 'Postal ID',
                'voters' => "Voter's ID",
                'prc' => 'PRC ID',
            ];
            
            $countryFormatted = $countryMap[strtolower($country)] ?? strtoupper($country);
            $documentFormatted = $documentMap[strtolower($document)] ?? ucfirst($document);
            
            return "{$countryFormatted} {$documentFormatted}";
        }
        
        return null;
    }
}
