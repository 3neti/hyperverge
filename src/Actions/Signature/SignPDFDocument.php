<?php

namespace LBHurtado\HyperVerge\Actions\Signature;

use Lorisleiva\Actions\Concerns\AsAction;
use phpseclib3\File\X509;

class SignPDFDocument
{
    use AsAction;

    /**
     * Sign a PDF document with digital signature.
     *
     * @param  string  $pdfPath  - Path to PDF file
     * @param  array  $signerInfo  - Signer details (name, email, etc)
     * @param  array  $certificateConfig  - Certificate paths and password
     * @return array - ['signed_path' => string, 'signature_info' => array]
     */
    public function handle(
        string $pdfPath,
        array $signerInfo,
        array $certificateConfig
    ): array {
        // 1. Validate inputs
        $this->validateInputs($pdfPath, $certificateConfig);

        // 2. Load certificate and private key
        $certificate = $this->loadCertificate($certificateConfig);

        // 3. Create signature appearance
        $signatureAppearance = $this->createSignatureAppearance($signerInfo);

        // 4. Sign the PDF
        $signedPath = $this->signPDF(
            $pdfPath,
            $certificate,
            $signatureAppearance
        );

        // 5. Generate signature info
        $signatureInfo = $this->getSignatureInfo($certificate, $signerInfo);

        return [
            'signed_path' => $signedPath,
            'signature_info' => $signatureInfo,
            'signer' => $signerInfo,
        ];
    }

    protected function validateInputs(string $pdfPath, array $config): void
    {
        if (! file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        if (! file_exists($config['cert_path'])) {
            throw new \InvalidArgumentException('Certificate not found: '.$config['cert_path']);
        }

        if (! file_exists($config['key_path'])) {
            throw new \InvalidArgumentException('Private key not found: '.$config['key_path']);
        }
    }

    protected function loadCertificate(array $config): array
    {
        // Load certificate and key content
        $certContent = file_get_contents($config['cert_path']);
        $keyContent = file_get_contents($config['key_path']);

        return [
            'cert' => $certContent,
            'key' => $keyContent,
            'password' => $config['password'] ?? '',
        ];
    }

    protected function createSignatureAppearance(array $signerInfo): array
    {
        return [
            'name' => $signerInfo['name'] ?? config('signature.appearance.name', 'HyperVerge KYC'),
            'reason' => $signerInfo['reason'] ?? config('signature.appearance.reason', 'KYC Verification Completed'),
            'location' => $signerInfo['location'] ?? config('signature.appearance.location', 'Philippines'),
            'contact_info' => $signerInfo['contact'] ?? config('signature.appearance.contact', 'admin@hyperverge.test'),
        ];
    }

    protected function signPDF(
        string $pdfPath,
        array $certificate,
        array $appearance
    ): string {
        // Create output path
        $signedPath = sys_get_temp_dir().'/signed_'.uniqid().'_'.basename($pdfPath);

        // Create FPDI instance (extends TCPDF with import capabilities)
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set document information
        $pdf->SetCreator('HyperVerge KYC');
        $pdf->SetAuthor($appearance['name']);
        $pdf->SetTitle('Digitally Signed Document');

        // Set signature certificate
        $pdf->setSignature(
            $certificate['cert'],
            $certificate['key'],
            $certificate['password'],
            '',
            2, // PKCS#7 detached signature
            [
                'Name' => $appearance['name'],
                'Reason' => $appearance['reason'],
                'Location' => $appearance['location'],
                'ContactInfo' => $appearance['contact_info'],
            ]
        );

        // Import existing PDF
        $pageCount = $pdf->setSourceFile($pdfPath);

        // Copy all pages from original PDF
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // Import page
            $tplId = $pdf->importPage($pageNo);

            // Get page size
            $size = $pdf->getTemplateSize($tplId);

            // Add page with same orientation and size
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);

            // Use imported page
            $pdf->useImportedPage($tplId);
        }

        // Add visible signature appearance on last page
        $position = config('signature.position', [
            'x' => 15,
            'y' => 15,
            'width' => 60,
            'height' => 20,
        ]);

        $pdf->setSignatureAppearance(
            $position['x'],
            $position['y'],
            $position['width'],
            $position['height'],
            -1, // Last page
            $appearance['name']
        );

        // Output signed PDF to file
        $pdf->Output($signedPath, 'F');

        return $signedPath;
    }

    protected function getSignatureInfo(array $certificate, array $signerInfo): array
    {
        // Parse certificate to extract info
        $x509 = new X509;
        $x509->loadX509($certificate['cert']);

        $subject = $x509->getSubjectDN(true);

        return [
            'signer_name' => $signerInfo['name'] ?? 'Unknown',
            'certificate_cn' => $subject['CN'] ?? 'Unknown',
            'certificate_o' => $subject['O'] ?? 'Unknown',
            'signature_date' => now()->toIso8601String(),
            'signature_type' => 'PKCS#7 Detached',
            'hash_algorithm' => 'SHA-256',
        ];
    }
}
