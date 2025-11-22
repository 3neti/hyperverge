<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use FilippoToso\PdfWatermarker\Facades\ImageWatermarker;
use FilippoToso\PdfWatermarker\Support\Position;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Add QR code watermark to signed PDF.
 * 
 * Embeds a QR code in the bottom-right corner (or configured position)
 * of a signed PDF document for easy verification.
 * 
 * Usage:
 * 
 * $qrCodePath = GenerateVerificationQRCode::getFilePath($verificationUrl);
 * $watermarkedPdf = AddQRWatermarkToPDF::run($signedPdfPath, $qrCodePath);
 */
class AddQRWatermarkToPDF
{
    use AsAction;

    /**
     * Add QR code watermark to PDF.
     *
     * @param string $pdfPath Absolute path to signed PDF
     * @param string $qrCodePath Absolute path to QR code image
     * @param int|null $page Page number to watermark (null = last page, 0 = all pages, 1+ = specific page)
     * @param string|null $position Position constant from FilippoToso\PdfWatermarker\Support\Position
     * @param int|null $size QR code size in pixels
     * @param int|null $opacity Opacity (0-100)
     * @return string Absolute path to watermarked PDF
     */
    public function handle(
        string $pdfPath,
        string $qrCodePath,
        ?int $page = null,
        ?string $position = null,
        ?int $size = null,
        ?int $opacity = null
    ): string {
        // Get configuration
        $config = config('hyperverge.document_signing.qr_watermark', []);
        
        // Use config defaults if not provided
        $page = $page ?? $config['page'] ?? -1; // -1 = last page
        $position = $position ?? $config['position'] ?? 'bottom-right';
        $size = $size ?? $config['size'] ?? 100;
        $opacity = $opacity ?? $config['opacity'] ?? 100;

        // Check if QR watermarking is enabled
        if (!($config['enabled'] ?? true)) {
            // Return original PDF path if disabled
            return $pdfPath;
        }

        // Prepare QR code image (resize if needed)
        $preparedQRPath = $this->prepareQRCode($qrCodePath, $size, $opacity);

        // Generate output path
        $outputPath = $this->generateOutputPath($pdfPath);

        // Determine pages to watermark
        $watermarker = ImageWatermarker::input($pdfPath)
            ->output($outputPath)
            ->watermark($preparedQRPath)
            ->position($this->mapPosition($position));

        // Apply to specific pages
        if ($page === 0) {
            // All pages
            $watermarker->pages('all');
        } elseif ($page === -1) {
            // Last page only (default)
            $watermarker->pages('last');
        } else {
            // Specific page number
            $watermarker->pages($page);
        }

        $watermarker->save();

        // Clean up temp QR if we created a resized version
        if ($preparedQRPath !== $qrCodePath) {
            @unlink($preparedQRPath);
        }

        return $outputPath;
    }

    /**
     * Prepare QR code image (resize and adjust opacity if needed).
     *
     * @param string $qrCodePath Original QR code path
     * @param int $size Target size in pixels
     * @param int $opacity Opacity (0-100)
     * @return string Path to prepared QR code
     */
    protected function prepareQRCode(string $qrCodePath, int $size, int $opacity): string
    {
        // If no modifications needed, return original
        if ($size === 300 && $opacity === 100) {
            return $qrCodePath;
        }

        $img = \Intervention\Image\Facades\Image::make($qrCodePath);

        // Resize if needed
        if ($img->width() !== $size || $img->height() !== $size) {
            $img->resize($size, $size, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Apply opacity if needed
        if ($opacity < 100) {
            $img->opacity($opacity);
        }

        // Save to temp location
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'qr_watermark_' . uniqid() . '.png';
        $path = Storage::path($tempDir . '/' . $filename);
        
        $img->save($path, 100, 'png');

        return $path;
    }

    /**
     * Map position string to PdfWatermarker position constant.
     *
     * @param string $position Position string (e.g., 'bottom-right', 'top-left')
     * @return string Position constant
     */
    protected function mapPosition(string $position): string
    {
        return match (strtolower($position)) {
            'top-left' => Position::TOP_LEFT,
            'top-center', 'top-centre' => Position::TOP_CENTER,
            'top-right' => Position::TOP_RIGHT,
            'middle-left', 'center-left', 'centre-left' => Position::MIDDLE_LEFT,
            'middle-center', 'center', 'centre' => Position::MIDDLE_CENTER,
            'middle-right', 'center-right', 'centre-right' => Position::MIDDLE_RIGHT,
            'bottom-left' => Position::BOTTOM_LEFT,
            'bottom-center', 'bottom-centre' => Position::BOTTOM_CENTER,
            'bottom-right' => Position::BOTTOM_RIGHT,
            default => Position::BOTTOM_RIGHT,
        };
    }

    /**
     * Generate output path for watermarked PDF.
     *
     * @param string $pdfPath Original PDF path
     * @return string Output path
     */
    protected function generateOutputPath(string $pdfPath): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'qr_watermarked_' . uniqid() . '.pdf';
        return Storage::path($tempDir . '/' . $filename);
    }

    /**
     * Add QR watermark using action call.
     *
     * @param string $pdfPath Absolute path to signed PDF
     * @param string $qrCodePath Absolute path to QR code image
     * @param int|null $page Page number to watermark
     * @param string|null $position Position constant
     * @param int|null $size QR code size in pixels
     * @param int|null $opacity Opacity (0-100)
     * @return string Absolute path to watermarked PDF
     */
    public static function run(
        string $pdfPath,
        string $qrCodePath,
        ?int $page = null,
        ?string $position = null,
        ?int $size = null,
        ?int $opacity = null
    ): string {
        return (new static)->handle($pdfPath, $qrCodePath, $page, $position, $size, $opacity);
    }
}
