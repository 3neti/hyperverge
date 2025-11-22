<?php

use LBHurtado\HyperVerge\Actions\Document\ProcessIdImageStamp;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

beforeEach(function () {
    // Create a test ID image
    $this->idImagePath = sys_get_temp_dir() . '/test_id.jpg';
    Image::canvas(800, 600, '#ffffff')
        ->text('TEST ID CARD', 400, 300, function ($font) {
            $font->size(24);
            $font->color('#000000');
            $font->align('center');
        })
        ->save($this->idImagePath);
        
    // Create QR code data URI
    $this->qrCodeDataUri = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../fixtures/qr-code.png'));
});

afterEach(function () {
    // Clean up
    @unlink($this->idImagePath);
    Storage::deleteDirectory(config('hyperverge.document_signing.temp_dir', 'tmp/document-signing'));
});

test('it creates stamp image from ID card', function () {
    $stampPath = ProcessIdImageStamp::run(
        idImagePath: $this->idImagePath,
        metadata: ['name' => 'John Doe', 'email' => 'john@example.com'],
        timestamp: 'Mon 20 1200H Jan 2025 UTC+0',
        qrCodeDataUri: $this->qrCodeDataUri
    );
    
    expect($stampPath)
        ->toBeString()
        ->and(file_exists($stampPath))->toBeTrue()
        ->and(mime_content_type($stampPath))->toBe('image/png');
        
    // Verify dimensions
    $image = Image::make($stampPath);
    expect($image->width())->toBe(1500)
        ->and($image->height())->toBe(800);
});

test('it creates stamp without logo when logo path is null', function () {
    $stampPath = ProcessIdImageStamp::run(
        idImagePath: $this->idImagePath,
        metadata: ['name' => 'John Doe'],
        timestamp: 'Mon 20 1200H Jan 2025 UTC+0',
        qrCodeDataUri: $this->qrCodeDataUri,
        logoPath: null
    );
    
    expect($stampPath)->toBeString()
        ->and(file_exists($stampPath))->toBeTrue();
});

test('it handles empty metadata gracefully', function () {
    $stampPath = ProcessIdImageStamp::run(
        idImagePath: $this->idImagePath,
        metadata: [],
        timestamp: 'Mon 20 1200H Jan 2025 UTC+0',
        qrCodeDataUri: $this->qrCodeDataUri
    );
    
    expect($stampPath)->toBeString()
        ->and(file_exists($stampPath))->toBeTrue();
});

test('it creates unique file names for multiple stamps', function () {
    $stamp1 = ProcessIdImageStamp::run(
        idImagePath: $this->idImagePath,
        metadata: ['name' => 'John Doe'],
        timestamp: 'Mon 20 1200H Jan 2025 UTC+0',
        qrCodeDataUri: $this->qrCodeDataUri
    );
    
    $stamp2 = ProcessIdImageStamp::run(
        idImagePath: $this->idImagePath,
        metadata: ['name' => 'Jane Doe'],
        timestamp: 'Mon 20 1300H Jan 2025 UTC+0',
        qrCodeDataUri: $this->qrCodeDataUri
    );
    
    expect($stamp1)->not->toBe($stamp2)
        ->and(file_exists($stamp1))->toBeTrue()
        ->and(file_exists($stamp2))->toBeTrue();
});
