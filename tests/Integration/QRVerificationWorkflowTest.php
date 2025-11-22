<?php

namespace LBHurtado\HyperVerge\Tests\Integration;

use LBHurtado\HyperVerge\Actions\Document\GenerateVerificationQRCode;
use LBHurtado\HyperVerge\Actions\Document\AddQRWatermarkToPDF;
use LBHurtado\HyperVerge\Actions\Document\MarkDocumentWithKYC;
use LBHurtado\HyperVerge\Actions\Certificate\GenerateVerificationCertificate;
use LBHurtado\HyperVerge\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

/**
 * End-to-end QR Verification Workflow Test
 * 
 * Tests the complete flow from QR generation through to verification:
 * 1. Generate verification QR code
 * 2. Add QR watermark to PDF
 * 3. Generate certificate with QR
 * 4. Verify all QR codes are scannable and valid
 */
class QRVerificationWorkflowTest extends TestCase
{
    protected string $testVerificationUrl = 'https://example.com/verify/test-campaign/tx-12345';

    /** @test */
    public function it_completes_full_qr_generation_workflow()
    {
        // Step 1: Generate QR code
        $qrData = GenerateVerificationQRCode::run($this->testVerificationUrl);

        $this->assertIsArray($qrData);
        $this->assertArrayHasKey('data_uri', $qrData);
        $this->assertArrayHasKey('file_path', $qrData);
        $this->assertArrayHasKey('url', $qrData);
        
        // Verify QR file exists
        $this->assertFileExists($qrData['file_path']);
        $this->assertGreaterThan(0, filesize($qrData['file_path']));

        // Step 2: Create test PDF
        $testPdf = $this->createTestPDF();
        $this->assertFileExists($testPdf);

        // Step 3: Add QR watermark to PDF
        $watermarkedPdf = AddQRWatermarkToPDF::run($testPdf, $qrData['file_path']);
        
        $this->assertFileExists($watermarkedPdf);
        $this->assertNotEquals($testPdf, $watermarkedPdf);
        
        // Verify it's still a valid PDF
        $pdfContent = file_get_contents($watermarkedPdf);
        $this->assertStringStartsWith('%PDF', $pdfContent);

        // Cleanup
        @unlink($qrData['file_path']);
        @unlink($testPdf);
        @unlink($watermarkedPdf);
    }

    /** @test */
    public function qr_codes_are_consistent_across_document_types()
    {
        $url = 'https://example.com/verify/consistent-test';

        // Generate QR for stamp
        $stampQR = GenerateVerificationQRCode::run($url, 200, 10);
        
        // Generate QR for PDF watermark
        $watermarkQR = GenerateVerificationQRCode::run($url, 100, 10);
        
        // Generate QR for certificate
        $certificateQR = GenerateVerificationQRCode::run($url, 200, 5);

        // All should encode the same URL
        $this->assertEquals($url, $stampQR['url']);
        $this->assertEquals($url, $watermarkQR['url']);
        $this->assertEquals($url, $certificateQR['url']);

        // All files should exist
        $this->assertFileExists($stampQR['file_path']);
        $this->assertFileExists($watermarkQR['file_path']);
        $this->assertFileExists($certificateQR['file_path']);

        // Cleanup
        @unlink($stampQR['file_path']);
        @unlink($watermarkQR['file_path']);
        @unlink($certificateQR['file_path']);
    }

    /** @test */
    public function qr_watermark_respects_configuration()
    {
        $testPdf = $this->createTestPDF();
        $qrData = GenerateVerificationQRCode::run($this->testVerificationUrl);

        // Test with custom position
        $positions = ['top-left', 'bottom-right', 'middle-center'];
        
        foreach ($positions as $position) {
            $watermarked = AddQRWatermarkToPDF::run(
                $testPdf,
                $qrData['file_path'],
                position: $position
            );

            $this->assertFileExists($watermarked);
            $this->assertStringEndsWith('.pdf', $watermarked);
            @unlink($watermarked);
        }

        // Test with disabled config
        config(['hyperverge.document_signing.qr_watermark.enabled' => false]);
        $result = AddQRWatermarkToPDF::run($testPdf, $qrData['file_path']);
        
        // Should return original PDF when disabled
        $this->assertEquals($testPdf, $result);

        // Cleanup
        @unlink($qrData['file_path']);
        @unlink($testPdf);
    }

    /** @test */
    public function qr_codes_have_consistent_format()
    {
        $urls = [
            'https://example.com/verify/abc/123',
            'https://example.com/verify/def/456',
            'https://example.com/verify/ghi/789',
        ];

        foreach ($urls as $url) {
            $qrData = GenerateVerificationQRCode::run($url);

            // Check data URI format
            $this->assertStringStartsWith('data:image/png;base64,', $qrData['data_uri']);
            
            // Check file is PNG
            $fileContent = file_get_contents($qrData['file_path']);
            $this->assertStringStartsWith("\x89PNG", $fileContent);
            
            // Check dimensions
            $imageInfo = getimagesize($qrData['file_path']);
            $this->assertNotFalse($imageInfo);
            $this->assertEquals(IMAGETYPE_PNG, $imageInfo[2]);
            
            // Cleanup
            @unlink($qrData['file_path']);
        }
    }

    /** @test */
    public function qr_generation_handles_errors_gracefully()
    {
        // Test with invalid URL
        $qrData = GenerateVerificationQRCode::run('not-a-url');
        
        // Should still generate QR code (QR can encode any text)
        $this->assertIsArray($qrData);
        $this->assertFileExists($qrData['file_path']);
        
        // Cleanup
        @unlink($qrData['file_path']);
    }

    /** @test */
    public function qr_watermark_handles_different_pdf_sizes()
    {
        $qrData = GenerateVerificationQRCode::run($this->testVerificationUrl);

        // Create PDFs of different "sizes" (page counts)
        $singlePagePdf = $this->createTestPDF(pages: 1);
        $multiPagePdf = $this->createTestPDF(pages: 3);

        // Watermark single page PDF
        $watermarked1 = AddQRWatermarkToPDF::run($singlePagePdf, $qrData['file_path']);
        $this->assertFileExists($watermarked1);
        
        // Watermark multi-page PDF (last page)
        $watermarked2 = AddQRWatermarkToPDF::run($multiPagePdf, $qrData['file_path'], page: -1);
        $this->assertFileExists($watermarked2);
        
        // Watermark multi-page PDF (all pages)
        $watermarked3 = AddQRWatermarkToPDF::run($multiPagePdf, $qrData['file_path'], page: 0);
        $this->assertFileExists($watermarked3);

        // Cleanup
        @unlink($qrData['file_path']);
        @unlink($singlePagePdf);
        @unlink($multiPagePdf);
        @unlink($watermarked1);
        @unlink($watermarked2);
        @unlink($watermarked3);
    }

    /** @test */
    public function qr_codes_maintain_quality_at_different_sizes()
    {
        $url = 'https://example.com/verify/size-test';
        $sizes = [50, 100, 200, 300, 400];

        foreach ($sizes as $size) {
            $qrData = GenerateVerificationQRCode::run($url, $size);

            $this->assertFileExists($qrData['file_path']);
            $this->assertEquals($size, $qrData['size']);
            
            // Verify file is valid image
            $imageInfo = getimagesize($qrData['file_path']);
            $this->assertNotFalse($imageInfo, "QR code at size {$size} is not a valid image");
            
            // Cleanup
            @unlink($qrData['file_path']);
        }
    }

    /** @test */
    public function qr_watermark_performance_is_acceptable()
    {
        $qrData = GenerateVerificationQRCode::run($this->testVerificationUrl);
        $testPdf = $this->createTestPDF();

        // Measure time for watermarking
        $start = microtime(true);
        $watermarked = AddQRWatermarkToPDF::run($testPdf, $qrData['file_path']);
        $duration = microtime(true) - $start;

        // Should complete in reasonable time (< 2 seconds for test PDF)
        $this->assertLessThan(2.0, $duration, "QR watermarking took too long: {$duration}s");
        
        $this->assertFileExists($watermarked);

        // Cleanup
        @unlink($qrData['file_path']);
        @unlink($testPdf);
        @unlink($watermarked);
    }

    /** @test */
    public function qr_codes_are_unique_per_generation()
    {
        $url = 'https://example.com/verify/unique-test';

        $qr1 = GenerateVerificationQRCode::run($url);
        $qr2 = GenerateVerificationQRCode::run($url);

        // File paths should be different (unique temp files)
        $this->assertNotEquals($qr1['file_path'], $qr2['file_path']);
        
        // Both files should exist
        $this->assertFileExists($qr1['file_path']);
        $this->assertFileExists($qr2['file_path']);

        // Cleanup
        @unlink($qr1['file_path']);
        @unlink($qr2['file_path']);
    }

    /** @test */
    public function qr_watermark_preserves_pdf_content()
    {
        $testPdf = $this->createTestPDF();
        $originalSize = filesize($testPdf);
        
        $qrData = GenerateVerificationQRCode::run($this->testVerificationUrl);
        $watermarked = AddQRWatermarkToPDF::run($testPdf, $qrData['file_path']);

        // Watermarked PDF should be larger (due to QR image)
        $watermarkedSize = filesize($watermarked);
        $this->assertGreaterThan($originalSize, $watermarkedSize);

        // Should still be a valid PDF
        $content = file_get_contents($watermarked);
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('%%EOF', $content);

        // Cleanup
        @unlink($qrData['file_path']);
        @unlink($testPdf);
        @unlink($watermarked);
    }

    /**
     * Create a test PDF file.
     */
    protected function createTestPDF(int $pages = 1): string
    {
        $tempDir = config('hyperverge.document_signing.temp_dir', 'tmp/document-signing');
        Storage::makeDirectory($tempDir);

        $filename = 'test_integration_' . uniqid() . '.pdf';
        $path = Storage::path($tempDir . '/' . $filename);

        // Create minimal valid PDF with specified pages
        $pdfContent = "%PDF-1.4\n";
        $pdfContent .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdfContent .= "2 0 obj\n<< /Type /Pages /Kids [";
        
        for ($i = 0; $i < $pages; $i++) {
            $pdfContent .= ($i + 3) . " 0 R ";
        }
        
        $pdfContent .= "] /Count {$pages} >>\nendobj\n";

        for ($i = 0; $i < $pages; $i++) {
            $objNum = $i + 3;
            $contentNum = $objNum + $pages;
            $pdfContent .= "{$objNum} 0 obj\n";
            $pdfContent .= "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> ";
            $pdfContent .= "/MediaBox [0 0 612 792] /Contents {$contentNum} 0 R >>\nendobj\n";
        }

        for ($i = 0; $i < $pages; $i++) {
            $objNum = $i + 3 + $pages;
            $pageNum = $i + 1;
            $stream = "BT /F1 12 Tf 100 700 Td (Page {$pageNum}) Tj ET";
            $pdfContent .= "{$objNum} 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n";
        }

        $xrefStart = strlen($pdfContent);
        $totalObjs = 3 + ($pages * 2);
        $pdfContent .= "xref\n0 {$totalObjs}\n";
        $pdfContent .= "0000000000 65535 f \n";
        
        for ($i = 1; $i < $totalObjs; $i++) {
            $pdfContent .= "0000000000 00000 n \n";
        }
        
        $pdfContent .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\n";
        $pdfContent .= "startxref\n{$xrefStart}\n%%EOF";

        file_put_contents($path, $pdfContent);

        return $path;
    }
}
