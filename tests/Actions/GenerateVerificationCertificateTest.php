<?php

namespace LBHurtado\HyperVerge\Tests\Actions;

use LBHurtado\HyperVerge\Actions\Certificate\GenerateVerificationCertificate;
use LBHurtado\HyperVerge\Actions\Certificate\CertificateData;
use LBHurtado\HyperVerge\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class GenerateVerificationCertificateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_certificate_pdf()
    {
        // Create a mock model with media
        $model = $this->createMockModelWithKYCData();

        // Mock KYC result
        $this->mockKYCResult();

        $certificatePath = GenerateVerificationCertificate::run(
            model: $model,
            transactionId: 'test_tx_123',
            options: [
                'verificationUrl' => 'https://example.com/verify/abc/123',
                'metadata' => ['campaign' => 'Test Campaign'],
            ]
        );

        $this->assertFileExists($certificatePath);
        $this->assertStringEndsWith('.pdf', $certificatePath);
        $this->assertGreaterThan(0, filesize($certificatePath));

        // Verify it's a valid PDF
        $content = file_get_contents($certificatePath);
        $this->assertStringStartsWith('%PDF', $content);

        // Cleanup
        @unlink($certificatePath);
    }

    /** @test */
    public function certificate_includes_qr_code()
    {
        $model = $this->createMockModelWithKYCData();
        $this->mockKYCResult();

        $verificationUrl = 'https://example.com/verify/test/qr123';

        $certificatePath = GenerateVerificationCertificate::run(
            model: $model,
            transactionId: 'test_tx_qr',
            options: ['verificationUrl' => $verificationUrl]
        );

        $this->assertFileExists($certificatePath);

        // PDF should be larger than minimal PDF (due to QR image embedded)
        $this->assertGreaterThan(5000, filesize($certificatePath));

        // Cleanup
        @unlink($certificatePath);
    }

    /** @test */
    public function certificate_data_extracts_from_kyc_result()
    {
        $kycResult = $this->createMockKYCResult();
        $model = $this->createMockModelWithKYCData();

        $data = CertificateData::fromKYCResult(
            $kycResult,
            $model,
            ['verificationUrl' => 'https://example.com/verify']
        );

        $this->assertInstanceOf(CertificateData::class, $data);
        $this->assertEquals('test_tx_456', $data->transactionId);
        $this->assertEquals('https://example.com/verify', $data->verificationUrl);
    }

    /** @test */
    public function certificate_includes_verification_url()
    {
        $model = $this->createMockModelWithKYCData();
        $this->mockKYCResult();

        $verificationUrl = 'https://example.com/verify/campaign123/tx456';

        $certificatePath = GenerateVerificationCertificate::run(
            model: $model,
            transactionId: 'test_tx_url',
            options: ['verificationUrl' => $verificationUrl]
        );

        $this->assertFileExists($certificatePath);

        // Read PDF content
        $content = file_get_contents($certificatePath);

        // URL should be embedded in PDF (in some form)
        // PDFs encode text, so we check for domain
        $this->assertStringContainsString('example.com', $content);

        // Cleanup
        @unlink($certificatePath);
    }

    /** @test */
    public function certificate_handles_missing_optional_data()
    {
        $model = $this->createMockModelWithKYCData();

        // Mock KYC result with minimal data
        $this->mockKYCResultMinimal();

        $certificatePath = GenerateVerificationCertificate::run(
            model: $model,
            transactionId: 'test_tx_minimal',
            options: ['verificationUrl' => 'https://example.com/verify']
        );

        $this->assertFileExists($certificatePath);
        $this->assertStringEndsWith('.pdf', $certificatePath);

        // Cleanup
        @unlink($certificatePath);
    }

    /** @test */
    public function certificate_generates_without_qr_if_url_missing()
    {
        $model = $this->createMockModelWithKYCData();
        $this->mockKYCResult();

        // Empty verification URL
        $certificatePath = GenerateVerificationCertificate::run(
            model: $model,
            transactionId: 'test_tx_no_qr',
            options: ['verificationUrl' => '']
        );

        // Certificate should still generate
        $this->assertFileExists($certificatePath);

        // Cleanup
        @unlink($certificatePath);
    }

    /**
     * Create a mock model with KYC media.
     */
    protected function createMockModelWithKYCData()
    {
        $model = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('getMedia')
            ->with('kyc_id_cards')
            ->andReturn(collect([]));
        $model->shouldReceive('getMedia')
            ->with('kyc_selfies')
            ->andReturn(collect([]));

        return $model;
    }

    /**
     * Mock a KYC result.
     */
    protected function mockKYCResult()
    {
        $this->app->instance(
            'LBHurtado\HyperVerge\Actions\Results\FetchKYCResult',
            Mockery::mock('alias:LBHurtado\HyperVerge\Actions\Results\FetchKYCResult', function ($mock) {
                $mock->shouldReceive('run')
                    ->andReturn($this->createMockKYCResult());
            })
        );
    }

    /**
     * Mock a minimal KYC result.
     */
    protected function mockKYCResultMinimal()
    {
        $this->app->instance(
            'LBHurtado\HyperVerge\Actions\Results\FetchKYCResult',
            Mockery::mock('alias:LBHurtado\HyperVerge\Actions\Results\FetchKYCResult', function ($mock) {
                $mock->shouldReceive('run')
                    ->andReturn($this->createMinimalKYCResult());
            })
        );
    }

    /**
     * Create a mock KYC result object.
     */
    protected function createMockKYCResult()
    {
        return (object) [
            'transactionId' => 'test_tx_456',
            'applicationStatus' => 'approved',
            'modules' => [
                (object) [
                    'details' => [
                        'fullName' => 'Juan Dela Cruz',
                        'dateOfBirth' => '1990-01-01',
                        'idType' => 'drivers_license',
                        'idNumber' => 'DL123456789',
                        'address' => '123 Test Street, Manila',
                    ],
                    'documentSelected' => 'dl',
                    'countrySelected' => 'phl',
                ],
            ],
        ];
    }

    /**
     * Create a minimal KYC result object.
     */
    protected function createMinimalKYCResult()
    {
        return (object) [
            'transactionId' => 'test_tx_minimal',
            'applicationStatus' => 'approved',
            'modules' => [
                (object) [
                    'details' => [],
                ],
            ],
        ];
    }
}
