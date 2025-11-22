<?php

namespace LBHurtado\HyperVerge\Actions\Certificate;

use Spatie\LaravelData\Data;

class CertificateData extends Data
{
    public function __construct(
        public string $fullName,
        public string $dateOfBirth,
        public string $idType,
        public string $idNumber,
        public ?string $address,
        public string $transactionId,
        public string $verificationDate,
        public string $verificationUrl,
        public ?string $idImagePath,
        public ?string $selfieImagePath,
        public array $metadata = [],
    ) {}

    public static function fromKYCResult($kycResult, $model, $options): self
    {
        // Extract data from KYC result - find ID card module
        $idModule = null;
        $details = [];
        
        foreach ($kycResult->modules as $module) {
            if (isset($module->details) && !empty($module->details)) {
                $idModule = $module;
                // Convert details to array if it's an object
                $details = is_array($module->details) ? $module->details : (array) $module->details;
                break;
            }
        }

        return new self(
            fullName: $details['fullName'] ?? 'N/A',
            dateOfBirth: $details['dateOfBirth'] ?? 'N/A',
            idType: $details['idType'] ?? ($idModule?->documentSelected ?? 'N/A'),
            idNumber: $details['idNumber'] ?? 'N/A',
            address: $details['address'] ?? null,
            transactionId: $kycResult->transactionId,
            verificationDate: now()->format('F d, Y H:i'),
            verificationUrl: $options['verificationUrl'] ?? '',
            idImagePath: static::extractIdImage($model),
            selfieImagePath: static::extractSelfie($model),
            metadata: $options['metadata'] ?? [],
        );
    }

    protected static function extractIdImage($model): ?string
    {
        $media = $model->getMedia('kyc_id_cards')->first();

        return $media ? $media->getPath() : null;
    }

    protected static function extractSelfie($model): ?string
    {
        $media = $model->getMedia('kyc_selfies')->first();

        return $media ? $media->getPath() : null;
    }
}
