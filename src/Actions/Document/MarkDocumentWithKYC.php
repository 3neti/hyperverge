<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Actions\Results\ExtractKYCImages;
use LBHurtado\HyperVerge\Contracts\{DocumentStoragePort, VerificationUrlResolver};
use LBHurtado\HyperVerge\Events\DocumentSigned;

class MarkDocumentWithKYC
{
    use AsAction;

    public function __construct(
        protected DocumentStoragePort $storage,
        protected VerificationUrlResolver $urlResolver
    ) {}

    /**
     * Mark/sign a document with KYC verification data.
     *
     * @param Model $model The model that owns the document (e.g., CampaignSubmission)
     * @param string $transactionId HyperVerge transaction ID
     * @param array $additionalMetadata Additional metadata to include in stamp
     * @param int $tile Tile position for signature (1-9)
     * @param string|null $logoPath Optional logo file path
     * @return array ['stamp' => media, 'signed_document' => media]
     */
    public function handle(
        Model $model,
        string $transactionId,
        array $additionalMetadata = [],
        int $tile = 1,
        ?string $logoPath = null
    ): array {
        // Fetch KYC result
        $kycResult = FetchKYCResult::run($transactionId);
        
        // Extract images
        $images = ExtractKYCImages::run($transactionId);
        $idImageUrl = $images['id_card_full'] ?? $images['id_card_cropped'] ?? null;
        
        if (!$idImageUrl) {
            throw new \RuntimeException("No ID image found in KYC result for transaction: {$transactionId}");
        }

        // Download ID image to temp location
        $idImagePath = $this->downloadIdImage($idImageUrl);

        // Get verification URL
        $verificationUrl = $this->urlResolver->resolve($model, $transactionId);

        // Generate QR code (returns array with data_uri and file_path)
        $qrCodeData = GenerateVerificationQRCode::run($verificationUrl);
        $qrCodeDataUri = $qrCodeData['data_uri'];
        $qrCodeFilePath = $qrCodeData['file_path'];

        // Prepare metadata
        $metadata = array_merge(
            $this->extractMetadata($kycResult),
            $additionalMetadata
        );

        // Format timestamp
        $timestamp = Carbon::parse($kycResult->requestTime ?? now())
            ->format(config('hyperverge.document_signing.stamp.timestamp.format', 'D d Hi\H M Y eO'));

        // Create stamp
        $stampPath = ProcessIdImageStamp::run(
            idImagePath: $idImagePath,
            metadata: $metadata,
            timestamp: $timestamp,
            qrCodeDataUri: $qrCodeDataUri,
            logoPath: $logoPath ?? $this->getDefaultLogoPath()
        );

        // Get original document
        $originalDocument = $this->storage->getDocument($model, 'documents');
        if (!$originalDocument) {
            throw new \RuntimeException("No document found for model: " . get_class($model));
        }

        $documentPath = $this->storage->getPath($originalDocument);

        // Stamp document with signature
        $signedDocumentPath = StampDocument::run($documentPath, $stampPath, $tile);

        // Add QR code watermark to signed PDF (bottom-right corner)
        $qrWatermarkedPath = AddQRWatermarkToPDF::run($signedDocumentPath, $qrCodeFilePath);

        // Store signed document and stamp (use QR watermarked version)
        $signedDocument = $this->storage->storeDocument(
            $model,
            $qrWatermarkedPath,
            'signed_documents',
            [
                'transaction_id' => $transactionId,
                'tile' => $tile,
                'signed_at' => now()->toIso8601String(),
                'qr_watermarked' => true,
                'verification_url' => $verificationUrl,
            ]
        );

        $signatureMark = $this->storage->storeDocument(
            $model,
            $stampPath,
            'signature_marks',
            [
                'transaction_id' => $transactionId,
                'tile' => $tile,
            ]
        );

        // Clean up temp files
        @unlink($idImagePath);
        @unlink($stampPath);
        @unlink($qrCodeFilePath);
        @unlink($signedDocumentPath);
        @unlink($qrWatermarkedPath);

        // Dispatch event
        DocumentSigned::dispatch($transactionId, $model, $signedDocument, $signatureMark, $metadata);

        return [
            'stamp' => $signatureMark,
            'signed_document' => $signedDocument,
        ];
    }

    /**
     * Download ID image from URL to temp location.
     */
    protected function downloadIdImage(string $url): string
    {
        $tempPath = sys_get_temp_dir() . '/kyc_id_' . uniqid() . '.jpg';
        
        $contents = file_get_contents($url, false, stream_context_create([
            'http' => [
                'timeout' => config('hyperverge.images.timeout', 30),
            ],
            'ssl' => [
                'verify_peer' => config('hyperverge.images.verify_ssl', true),
            ],
        ]));

        file_put_contents($tempPath, $contents);

        return $tempPath;
    }

    /**
     * Extract metadata from KYC result.
     */
    protected function extractMetadata($kycResult): array
    {
        $metadata = [];

        // Extract from ID card module if available
        foreach ($kycResult->modules ?? [] as $module) {
            if (method_exists($module, 'toArray')) {
                $data = $module->toArray();
                
                if (isset($data['details'])) {
                    $metadata = array_merge($metadata, [
                        'name' => $data['details']['name'] ?? null,
                        'dob' => $data['details']['dateOfBirth'] ?? null,
                        'id_number' => $data['details']['idNumber'] ?? null,
                        'id_type' => $data['documentSelected'] ?? null,
                        'country' => $data['countrySelected'] ?? null,
                    ]);
                }
            }
        }

        return array_filter($metadata);
    }

    /**
     * Get default logo path.
     */
    protected function getDefaultLogoPath(): ?string
    {
        $logoFile = config('hyperverge.document_signing.stamp.logo.file');
        
        if (!$logoFile) {
            return null;
        }

        // Check if absolute path
        if (file_exists($logoFile)) {
            return $logoFile;
        }

        // Check in public/images
        $publicPath = public_path('images/' . $logoFile);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Check in storage
        $storagePath = storage_path('app/public/' . $logoFile);
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        return null;
    }

    /**
     * Mark document as action call.
     */
    public static function run(
        Model $model,
        string $transactionId,
        array $additionalMetadata = [],
        int $tile = 1,
        ?string $logoPath = null
    ): array {
        return app(static::class)->handle($model, $transactionId, $additionalMetadata, $tile, $logoPath);
    }

    /**
     * Dispatch as job.
     */
    public function asJob(
        Model $model,
        string $transactionId,
        array $additionalMetadata = [],
        int $tile = 1,
        ?string $logoPath = null
    ): void {
        $this->handle($model, $transactionId, $additionalMetadata, $tile, $logoPath);
    }
}
