<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
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
     * @return array
     */
    public function handle(string $url, int $size = 300, int $margin = 10): array
    {
        // Get configuration
        $config = config('hyperverge.qr_code', []);
        $size = $config['default_size'] ?? $size;
        $margin = $config['margin'] ?? $margin;

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
        $dataUri = 'data:image/png;base64,' . base64_encode($enhancedData);

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
     *
     * @param  string  $pngData
     * @param  int  $originalSize
     * @return string
     */
    protected function enhanceQRCode(string $pngData, int $originalSize): string
    {
        // Load the QR code image
        $img = Image::make($pngData);
        $width = $img->width();
        $height = $img->height();

        // Create new canvas with white background and padding
        $padding = 20;
        $canvas = Image::canvas($width + ($padding * 2), $height + ($padding * 2), '#ffffff');
        
        // Insert QR code in center
        $canvas->insert($img, 'center');
        
        // Add black border for visibility
        $canvas->rectangle(0, 0, $width + ($padding * 2) - 1, $height + ($padding * 2) - 1, function($draw) {
            $draw->border(3, '#000000');
        });

        // Save to temp file
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'qr_verification_' . uniqid() . '.png';
        $path = Storage::path($tempDir . '/' . $filename);
        
        $canvas->save($path);

        return $path;
    }

    /**
     * Get QR code as data URI only.
     *
     * @param  string  $url
     * @param  int  $size
     * @param  int  $margin
     * @return string
     */
    public static function getDataUri(string $url, int $size = 300, int $margin = 10): string
    {
        return static::run($url, $size, $margin)['data_uri'];
    }

    /**
     * Get QR code file path only.
     *
     * @param  string  $url
     * @param  int  $size
     * @param  int  $margin
     * @return string
     */
    public static function getFilePath(string $url, int $size = 300, int $margin = 10): string
    {
        return static::run($url, $size, $margin)['file_path'];
    }
}
