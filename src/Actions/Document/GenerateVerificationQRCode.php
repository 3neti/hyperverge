<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generate QR code for document verification URL.
 *
 * Creates a PNG QR code that links to the verification page.
 * Returns both data URI (for embedding in images) and file path (for PDF watermarking).
 *
 * Usage:
 *
 * $qr = GenerateVerificationQRCode::run($verificationUrl);
 *
 * // Use data URI for ProcessIdImageStamp
 * ProcessIdImageStamp::run($idImage, $metadata, $timestamp, $qr['data_uri']);
 *
 * // Use file path for PDF watermarking
 * ImageWatermarker::watermark($pdf, $qr['file_path']);
 */
class GenerateVerificationQRCode
{
    use AsAction;

    /**
     * Execute the action.
     *
     * @param  string  $url  - Verification URL to encode
     * @param  int  $size  - QR code size in pixels
     * @param  int  $margin  - Margin around QR code
     */
    public function handle(string $url, ?int $size = null, ?int $margin = null): array
    {
        $config = config('hyperverge.qr_code', []);
        $size ??= $config['default_size'] ?? 300;
        $margin ??= $config['margin'] ?? 10;

        // Create QR code
        $qrCode = new QrCode(
            data: $url,
            size: $size,
            margin: $margin
        );

        // Generate PNG
        $writer = new PngWriter;
        $result = $writer->write($qrCode);

        // Add white background and black border for visibility
        $enhancedPath = $this->enhanceQRCode($result->getString(), $size);

        // Load enhanced QR code for data URI
        $enhancedData = file_get_contents($enhancedPath);
        $dataUri = 'data:image/png;base64,'.base64_encode($enhancedData);

        return [
            'data_uri' => $dataUri,
            'file_path' => $enhancedPath,
            'url' => $url,
            'size' => $size,
            'margin' => $margin,
        ];
    }

    /**
     * Enhance QR code with white background and black border for visibility.
     */
    protected function enhanceQRCode(string $pngData, int $originalSize): string
    {
        // Load the QR code image
        $manager = ImageManager::gd();
        $img = $manager->read($pngData);
        $width = $img->width();
        $height = $img->height();

        // Create new canvas with white background and padding
        $padding = 20;
        $canvas = $manager
            ->create($width + ($padding * 2), $height + ($padding * 2))
            ->fill('#ffffff');

        // Insert QR code in center
        $canvas->place($img, 'center');

        // Add black border for visibility
        $canvas->drawRectangle(0, 0, function (RectangleFactory $rectangle) use ($canvas): void {
            $rectangle
                ->size($canvas->width(), $canvas->height())
                ->border('#000000', 3);
        });

        // Save to temp file
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'qr_verification_'.uniqid().'.png';
        $path = Storage::path($tempDir.'/'.$filename);

        $canvas->toPng()->save($path);

        return $path;
    }

    /**
     * Get QR code as data URI only.
     */
    public static function getDataUri(string $url, int $size = 300, int $margin = 10): string
    {
        return static::run($url, $size, $margin)['data_uri'];
    }

    /**
     * Get QR code file path only.
     */
    public static function getFilePath(string $url, int $size = 300, int $margin = 10): string
    {
        return static::run($url, $size, $margin)['file_path'];
    }
}
