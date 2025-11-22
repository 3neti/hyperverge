<?php

namespace LBHurtado\HyperVerge\Tests\Actions;

use LBHurtado\HyperVerge\Actions\Document\GenerateVerificationQRCode;
use LBHurtado\HyperVerge\Tests\TestCase;

class GenerateVerificationQRCodeTest extends TestCase
{
    /** @test */
    public function it_generates_qr_code_with_data_uri_and_file_path()
    {
        $url = 'https://example.com/verify/abc123/xyz789';

        $result = GenerateVerificationQRCode::run($url);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data_uri', $result);
        $this->assertArrayHasKey('file_path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('margin', $result);

        // Verify data URI format
        $this->assertStringStartsWith('data:image/png;base64,', $result['data_uri']);

        // Verify file exists
        $this->assertFileExists($result['file_path']);

        // Verify URL matches
        $this->assertEquals($url, $result['url']);

        // Clean up temp file
        @unlink($result['file_path']);
    }

    /** @test */
    public function it_generates_qr_code_with_custom_size()
    {
        $url = 'https://example.com/verify/test';
        $size = 400;

        $result = GenerateVerificationQRCode::run($url, $size);

        $this->assertEquals($size, $result['size']);

        // Clean up
        @unlink($result['file_path']);
    }

    /** @test */
    public function it_can_get_data_uri_only()
    {
        $url = 'https://example.com/verify/test';

        $dataUri = GenerateVerificationQRCode::getDataUri($url);

        $this->assertIsString($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_get_file_path_only()
    {
        $url = 'https://example.com/verify/test';

        $filePath = GenerateVerificationQRCode::getFilePath($url);

        $this->assertIsString($filePath);
        $this->assertFileExists($filePath);

        // Clean up
        @unlink($filePath);
    }

    /** @test */
    public function it_creates_unique_temp_files()
    {
        $url = 'https://example.com/verify/test';

        $result1 = GenerateVerificationQRCode::run($url);
        $result2 = GenerateVerificationQRCode::run($url);

        // Different files should be created
        $this->assertNotEquals($result1['file_path'], $result2['file_path']);

        // Both should exist
        $this->assertFileExists($result1['file_path']);
        $this->assertFileExists($result2['file_path']);

        // Clean up
        @unlink($result1['file_path']);
        @unlink($result2['file_path']);
    }
}
