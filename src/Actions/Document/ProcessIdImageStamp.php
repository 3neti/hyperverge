<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Intervention\Image\Image as ImageInstance;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessIdImageStamp
{
    use AsAction;

    /**
     * Create a composite signature stamp from KYC ID image.
     *
     * @param string $idImagePath Absolute path to ID image
     * @param array $metadata KYC metadata (name, email, etc.)
     * @param string $timestamp Formatted timestamp
     * @param string $qrCodeDataUri QR code data URI (base64)
     * @param string|null $logoPath Optional logo file path
     * @return string Absolute path to generated stamp PNG
     */
    public function handle(
        string $idImagePath,
        array $metadata,
        string $timestamp,
        string $qrCodeDataUri,
        ?string $logoPath = null
    ): string {
        $config = config('hyperverge.document_signing.stamp');

        // Create base image from ID card
        $stamp = $this->createBaseImage($idImagePath, $config);

        // Apply logo watermark
        if ($logoPath && file_exists($logoPath)) {
            $stamp = $this->applyLogoWatermark($stamp, $logoPath, $config['logo']);
        }

        // Add metadata text (top-right)
        $stamp = $this->addMetadataText($stamp, $metadata, $config['metadata']);

        // Add timestamp banner (bottom)
        $stamp = $this->addTimestampBanner($stamp, $timestamp, $config['timestamp']);

        // Add QR code (bottom-left)
        $stamp = $this->addQrCode($stamp, $qrCodeDataUri, $config['qr_code']);

        // Save to temp directory
        return $this->saveStamp($stamp);
    }

    /**
     * Create base image from ID card.
     */
    protected function createBaseImage(string $idImagePath, array $config): ImageInstance
    {
        return Image::make($idImagePath)
            ->resize($config['width'], $config['height'], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->resizeCanvas($config['width'], $config['height'], 'center', false, 'ffffff');
    }

    /**
     * Apply logo watermark overlay.
     */
    protected function applyLogoWatermark(ImageInstance $stamp, string $logoPath, array $config): ImageInstance
    {
        $logo = Image::make($logoPath)
            ->opacity($config['opacity'])
            ->rotate($config['angle']);

        return $stamp->insert($logo, $config['position']);
    }

    /**
     * Add metadata text (name, email, etc.) to top-right.
     */
    protected function addMetadataText(ImageInstance $stamp, array $metadata, array $config): ImageInstance
    {
        $text = json_encode(array_filter($metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        $stamp->text($text, $stamp->width() - 10, 10, function ($font) use ($config) {
            $font->file($this->getFontPath($config['font']));
            $font->size($config['size']);
            $font->color($config['color']);
            $font->align('right');
            $font->valign('top');
        });

        return $stamp;
    }

    /**
     * Add timestamp banner at bottom.
     */
    protected function addTimestampBanner(ImageInstance $stamp, string $timestamp, array $config): ImageInstance
    {
        $height = $config['size'] + 16;
        $y = $stamp->height() - $height;

        // Draw background rectangle
        $stamp->rectangle(0, $y, $stamp->width(), $stamp->height(), function ($draw) use ($config) {
            $draw->background($config['background']);
            $draw->border(1, '#67C23A');
        });

        // Add timestamp text
        $stamp->text($timestamp, 10, $y + 8, function ($font) use ($config) {
            $font->file($this->getFontPath($config['font']));
            $font->size($config['size']);
            $font->color($config['color']);
            $font->align('left');
            $font->valign('top');
        });

        return $stamp;
    }

    /**
     * Add QR code to bottom-left.
     */
    protected function addQrCode(ImageInstance $stamp, string $qrCodeDataUri, array $config): ImageInstance
    {
        $qrCode = Image::make($qrCodeDataUri)
            ->resize($config['size'], $config['size'])
            ->opacity($config['opacity']);

        return $stamp->insert($qrCode, $config['position']);
    }

    /**
     * Save stamp to temp directory.
     */
    protected function saveStamp(ImageInstance $stamp): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'stamp_' . uniqid() . '.png';
        $path = Storage::path($tempDir . '/' . $filename);

        $stamp->save($path, 100, 'png');

        return $path;
    }

    /**
     * Get font file path.
     */
    protected function getFontPath(string $font): string
    {
        // Check if absolute path
        if (file_exists($font)) {
            return $font;
        }

        // Check in public/fonts
        $publicPath = public_path('fonts/' . $font);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Check in storage
        $storagePath = storage_path('fonts/' . $font);
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        // Fallback to Intervention's bundled fonts (DejaVuSans.ttf)
        // These are typically in vendor/intervention/image/src/Intervention/Image/Gd/Fonts/
        $vendorPath = base_path('vendor/intervention/image/src/Intervention/Image/Gd/Fonts/' . $font);
        if (file_exists($vendorPath)) {
            return $vendorPath;
        }

        // Last resort: use numbered font (1-5) which are built into GD
        return 3; // Default GD font
    }

    /**
     * Create stamp from action call.
     */
    public static function run(
        string $idImagePath,
        array $metadata,
        string $timestamp,
        string $qrCodeDataUri,
        ?string $logoPath = null
    ): string {
        return (new static)->handle($idImagePath, $metadata, $timestamp, $qrCodeDataUri, $logoPath);
    }
}
