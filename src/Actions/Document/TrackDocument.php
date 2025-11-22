<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use FilippoToso\PdfWatermarker\Facades\ImageWatermarker;
use FilippoToso\PdfWatermarker\Support\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Contracts\DocumentStoragePort;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TrackDocument
{
    use AsAction;

    public function __construct(
        protected DocumentStoragePort $storage
    ) {}

    /**
     * Add tracking QR code to document.
     *
     * @param Model $model The model that owns the document
     * @param string $trackingUrl URL to embed in QR code
     * @return mixed The tracked document media object
     */
    public function handle(Model $model, string $trackingUrl): mixed
    {
        $config = config('hyperverge.document_signing.tracking');

        // Get original document
        $originalDocument = $this->storage->getDocument($model, 'documents');
        if (!$originalDocument) {
            throw new \RuntimeException("No document found for model: " . get_class($model));
        }

        $documentPath = $this->storage->getPath($originalDocument);

        // Generate QR code image
        $qrCodePath = $this->generateQrCodeImage($trackingUrl, $config['qr_code_size']);

        // Apply QR code to PDF
        $trackedDocumentPath = $this->applyQrCode($documentPath, $qrCodePath, $config);

        // Store tracked document
        $trackedDocument = $this->storage->storeDocument(
            $model,
            $trackedDocumentPath,
            'tracked_documents',
            [
                'tracking_url' => $trackingUrl,
                'tracked_on' => now()->toIso8601String(),
            ]
        );

        // Clean up temp files
        @unlink($qrCodePath);
        @unlink($trackedDocumentPath);

        return $trackedDocument;
    }

    /**
     * Generate QR code image.
     */
    protected function generateQrCodeImage(string $url, int $size): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $qrCodePath = Storage::path($tempDir . '/qr_' . uniqid() . '.png');

        // Generate QR code
        $qrCode = QrCode::format('png')
            ->size($size)
            ->margin(10)
            ->generate($url);

        // Save to file
        Image::make($qrCode)->save($qrCodePath);

        return $qrCodePath;
    }

    /**
     * Apply QR code to PDF document.
     */
    protected function applyQrCode(string $documentPath, string $qrCodePath, array $config): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $outputPath = Storage::path($tempDir . '/tracked_' . uniqid() . '.pdf');

        // Apply watermark (QR code) to first page only
        ImageWatermarker::input($documentPath)
            ->output($outputPath)
            ->watermark($qrCodePath)
            ->position($this->mapPosition($config['position']))
            ->resolution(300)
            ->pageRange($config['page']) // First page only
            ->save();

        return $outputPath;
    }

    /**
     * Map position string to constant.
     */
    protected function mapPosition(string $position): string
    {
        return match ($position) {
            'top-left' => Position::TOP_LEFT,
            'top-center' => Position::TOP_CENTER,
            'top-right' => Position::TOP_RIGHT,
            'middle-left' => Position::MIDDLE_LEFT,
            'middle-center' => Position::MIDDLE_CENTER,
            'middle-right' => Position::MIDDLE_RIGHT,
            'bottom-left' => Position::BOTTOM_LEFT,
            'bottom-center' => Position::BOTTOM_CENTER,
            'bottom-right' => Position::BOTTOM_RIGHT,
            default => Position::TOP_RIGHT,
        };
    }

    /**
     * Track document as action call.
     */
    public static function run(Model $model, string $trackingUrl): mixed
    {
        return app(static::class)->handle($model, $trackingUrl);
    }

    /**
     * Dispatch as job.
     */
    public function asJob(Model $model, string $trackingUrl): void
    {
        $this->handle($model, $trackingUrl);
    }
}
