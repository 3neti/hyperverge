<?php

namespace LBHurtado\HyperVerge\Tests\Actions;

use LBHurtado\HyperVerge\Actions\Document\AddQRWatermarkToPDF;
use LBHurtado\HyperVerge\Actions\Document\GenerateVerificationQRCode;
use LBHurtado\HyperVerge\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class AddQRWatermarkToPDFTest extends TestCase
{
    protected string $testPdfPath;
    protected string $testQRPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test PDF
        $this->testPdfPath = $this->createTestPDF();

        // Generate test QR code
        $qrData = GenerateVerificationQRCode::run('https://example.com/verify/test');
        $this->testQRPath = $qrData['file_path'];
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        @unlink($this->testPdfPath);
        @unlink($this->testQRPath);

        parent::tearDown();
    }

    /** @test */
    public function it_adds_qr_watermark_to_pdf()
    {
        $watermarkedPath = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath);

        $this->assertFileExists($watermarkedPath);
        $this->assertStringEndsWith('.pdf', $watermarkedPath);
        $this->assertGreaterThan(0, filesize($watermarkedPath));

        // Verify it's a valid PDF
        $this->assertStringStartsWith('%PDF', file_get_contents($watermarkedPath, false, null, 0, 4));

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_applies_watermark_to_last_page_by_default()
    {
        $watermarkedPath = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath);

        $this->assertFileExists($watermarkedPath);

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_applies_watermark_to_all_pages_when_specified()
    {
        $watermarkedPath = AddQRWatermarkToPDF::run(
            $this->testPdfPath,
            $this->testQRPath,
            page: 0 // 0 = all pages
        );

        $this->assertFileExists($watermarkedPath);

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_applies_watermark_to_specific_page()
    {
        $watermarkedPath = AddQRWatermarkToPDF::run(
            $this->testPdfPath,
            $this->testQRPath,
            page: 1 // First page
        );

        $this->assertFileExists($watermarkedPath);

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_supports_different_positions()
    {
        $positions = [
            'top-left',
            'top-center',
            'top-right',
            'middle-left',
            'middle-center',
            'middle-right',
            'bottom-left',
            'bottom-center',
            'bottom-right',
        ];

        foreach ($positions as $position) {
            $watermarkedPath = AddQRWatermarkToPDF::run(
                $this->testPdfPath,
                $this->testQRPath,
                position: $position
            );

            $this->assertFileExists($watermarkedPath, "Failed for position: {$position}");
            @unlink($watermarkedPath);
        }
    }

    /** @test */
    public function it_supports_custom_qr_size()
    {
        $watermarkedPath = AddQRWatermarkToPDF::run(
            $this->testPdfPath,
            $this->testQRPath,
            size: 150 // Custom size
        );

        $this->assertFileExists($watermarkedPath);

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_supports_custom_opacity()
    {
        $watermarkedPath = AddQRWatermarkToPDF::run(
            $this->testPdfPath,
            $this->testQRPath,
            opacity: 80 // 80% opacity
        );

        $this->assertFileExists($watermarkedPath);

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_respects_disabled_qr_watermark_config()
    {
        // Disable QR watermarking
        config(['hyperverge.document_signing.qr_watermark.enabled' => false]);

        $result = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath);

        // Should return original PDF path when disabled
        $this->assertEquals($this->testPdfPath, $result);
    }

    /** @test */
    public function it_uses_config_defaults()
    {
        config([
            'hyperverge.document_signing.qr_watermark.enabled' => true,
            'hyperverge.document_signing.qr_watermark.position' => 'top-left',
            'hyperverge.document_signing.qr_watermark.size' => 150,
            'hyperverge.document_signing.qr_watermark.page' => 1,
            'hyperverge.document_signing.qr_watermark.opacity' => 90,
        ]);

        $watermarkedPath = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath);

        $this->assertFileExists($watermarkedPath);

        // Cleanup
        @unlink($watermarkedPath);
    }

    /** @test */
    public function it_creates_unique_output_files()
    {
        $path1 = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath);
        $path2 = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath);

        $this->assertNotEquals($path1, $path2);
        $this->assertFileExists($path1);
        $this->assertFileExists($path2);

        // Cleanup
        @unlink($path1);
        @unlink($path2);
    }

    /** @test */
    public function it_prepares_qr_code_with_different_sizes()
    {
        // Test with small QR
        $smallPath = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath, size: 50);
        $this->assertFileExists($smallPath);

        // Test with large QR
        $largePath = AddQRWatermarkToPDF::run($this->testPdfPath, $this->testQRPath, size: 200);
        $this->assertFileExists($largePath);

        // Cleanup
        @unlink($smallPath);
        @unlink($largePath);
    }

    /**
     * Create a simple test PDF file.
     *
     * @return string Absolute path to test PDF
     */
    protected function createTestPDF(): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'test_pdf_' . uniqid() . '.pdf';
        $path = Storage::path($tempDir . '/' . $filename);

        // Create minimal valid PDF
        $pdfContent = <<<PDF
%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj
2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj
3 0 obj
<<
/Type /Page
/Parent 2 0 R
/Resources <<
/Font <<
/F1 <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
>>
>>
/MediaBox [0 0 612 792]
/Contents 4 0 R
>>
endobj
4 0 obj
<<
/Length 44
>>
stream
BT
/F1 12 Tf
100 700 Td
(Test PDF) Tj
ET
endstream
endobj
xref
0 5
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000317 00000 n 
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
410
%%EOF
PDF;

        file_put_contents($path, $pdfContent);

        return $path;
    }
}
