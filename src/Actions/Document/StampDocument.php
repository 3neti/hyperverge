<?php

namespace LBHurtado\HyperVerge\Actions\Document;

use FilippoToso\PdfWatermarker\Facades\ImageWatermarker;
use FilippoToso\PdfWatermarker\Support\Position;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Contracts\TileAllocator;

class StampDocument
{
    use AsAction;

    public function __construct(
        protected TileAllocator $tileAllocator
    ) {}

    /**
     * Apply signature stamp to PDF document.
     *
     * @param string $documentPath Absolute path to PDF document
     * @param string $stampPath Absolute path to stamp image (PNG)
     * @param int $tile Tile position (1-9 for 3x3 grid)
     * @return string Absolute path to stamped PDF
     */
    public function handle(string $documentPath, string $stampPath, int $tile = 1): string
    {
        $config = config('hyperverge.document_signing.watermark');
        $position = $this->tileAllocator->getTilePosition($tile);

        // Generate output path
        $outputPath = $this->generateOutputPath($documentPath);

        // Apply watermark
        ImageWatermarker::input($documentPath)
            ->output($outputPath)
            ->watermark($stampPath)
            ->resolution($config['resolution'])
            ->position($this->mapPosition($position))
            ->save();

        return $outputPath;
    }

    /**
     * Map tile position to PdfWatermarker position constant.
     */
    protected function mapPosition(array $position): string
    {
        $vertical = $position['vertical'];
        $horizontal = $position['horizontal'];

        return match ([$vertical, $horizontal]) {
            ['top', 'left'] => Position::TOP_LEFT,
            ['top', 'center'] => Position::TOP_CENTER,
            ['top', 'right'] => Position::TOP_RIGHT,
            ['center', 'left'] => Position::MIDDLE_LEFT,
            ['center', 'center'] => Position::MIDDLE_CENTER,
            ['center', 'right'] => Position::MIDDLE_RIGHT,
            ['bottom', 'left'] => Position::BOTTOM_LEFT,
            ['bottom', 'center'] => Position::BOTTOM_CENTER,
            ['bottom', 'right'] => Position::BOTTOM_RIGHT,
            default => Position::BOTTOM_RIGHT,
        };
    }

    /**
     * Generate output path for stamped document.
     */
    protected function generateOutputPath(string $documentPath): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'signed_' . uniqid() . '.pdf';
        return Storage::path($tempDir . '/' . $filename);
    }

    /**
     * Stamp a document using action call.
     */
    public static function run(string $documentPath, string $stampPath, int $tile = 1): string
    {
        return app(static::class)->handle($documentPath, $stampPath, $tile);
    }
}
