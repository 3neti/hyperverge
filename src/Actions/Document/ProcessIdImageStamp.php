<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessIdImageStamp
{
    use AsAction;

    /**
     * Create a composite signature stamp from KYC ID image.
     *
     * @param  string  $idImagePath  Absolute path to ID image
     * @param  array  $metadata  KYC metadata (name, email, etc.)
     * @param  string  $timestamp  Formatted timestamp
     * @param  string  $qrCodeDataUri  QR code data URI (base64)
     * @param  string|null  $logoPath  Optional logo file path
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
    protected function createBaseImage(string $idImagePath, array $config): ImageInterface
    {
        return ImageManager::gd()
            ->read($idImagePath)
            ->scaleDown(width: $config['width'], height: $config['height'])
            ->resizeCanvas($config['width'], $config['height'], 'ffffff', 'center');
    }

    /**
     * Apply logo watermark overlay.
     */
    protected function applyLogoWatermark(ImageInterface $stamp, string $logoPath, array $config): ImageInterface
    {
        $logo = ImageManager::gd()
            ->read($logoPath)
            ->rotate($config['angle']);

        return $stamp->place(
            $logo,
            $config['position'],
            opacity: $config['opacity'],
        );
    }

    /**
     * Add metadata text (name, email, etc.) to top-right.
     */
    protected function addMetadataText(ImageInterface $stamp, array $metadata, array $config): ImageInterface
    {
        $text = json_encode(array_filter($metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $stamp->text($text, $stamp->width() - 10, 10, function (FontFactory $font) use ($config): void {
            if ($fontPath = $this->getFontPath($config['font'])) {
                $font->filename($fontPath);
            }

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
    protected function addTimestampBanner(ImageInterface $stamp, string $timestamp, array $config): ImageInterface
    {
        $height = $config['size'] + 16;
        $y = $stamp->height() - $height;

        // Draw background rectangle
        $stamp->drawRectangle(0, $y, function (RectangleFactory $rectangle) use ($config, $height, $stamp): void {
            $rectangle
                ->size($stamp->width(), $height)
                ->background($config['background'])
                ->border('#67C23A', 1);
        });

        // Add timestamp text
        $stamp->text($timestamp, 10, $y + 8, function (FontFactory $font) use ($config): void {
            if ($fontPath = $this->getFontPath($config['font'])) {
                $font->filename($fontPath);
            }

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
    protected function addQrCode(ImageInterface $stamp, string $qrCodeDataUri, array $config): ImageInterface
    {
        $qrCode = ImageManager::gd()
            ->read($qrCodeDataUri)
            ->resize($config['size'], $config['size']);

        return $stamp->place(
            $qrCode,
            $config['position'],
            opacity: $config['opacity'],
        );
    }

    /**
     * Save stamp to temp directory.
     */
    protected function saveStamp(ImageInterface $stamp): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'stamp_'.uniqid().'.png';
        $path = Storage::path($tempDir.'/'.$filename);

        $stamp->toPng()->save($path);

        return $path;
    }

    /**
     * Get font file path.
     */
    protected function getFontPath(string $font): ?string
    {
        // Check if absolute path
        if (file_exists($font)) {
            return $font;
        }

        // Check in public/fonts
        $publicPath = public_path('fonts/'.$font);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Check in storage
        $storagePath = storage_path('fonts/'.$font);
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        foreach ([
            '/usr/share/fonts/truetype/dejavu/'.$font,
            '/System/Library/Fonts/Supplemental/'.$font,
            '/Library/Fonts/'.$font,
        ] as $systemPath) {
            if (file_exists($systemPath)) {
                return $systemPath;
            }
        }

        return null;
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
