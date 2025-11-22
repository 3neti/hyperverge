<?php

namespace LBHurtado\HyperVerge\Actions\Certificate\Layouts;

use LBHurtado\HyperVerge\Actions\Certificate\CertificateData;
use LBHurtado\HyperVerge\Actions\Document\GenerateVerificationQRCode;

class DefaultCertificateLayout
{
    public function render(CertificateData $data): string
    {
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false); // Disable auto page breaks
        $pdf->AddPage();
        $pdf->SetMargins(20, 15, 20);

        // Header
        $this->renderHeader($pdf);

        // Title
        $this->renderTitle($pdf);

        // Verification Status Badge
        $this->renderVerificationBadge($pdf);

        // Personal Information
        $this->renderPersonalInfo($pdf, $data);

        // Images (ID + QR Code)
        $this->renderImages($pdf, $data);

        // Footer
        $this->renderFooter($pdf, $data);

        // Save to temp file
        $path = sys_get_temp_dir().'/cert_'.uniqid().'.pdf';
        $pdf->Output('F', $path);

        return $path;
    }

    protected function renderHeader($pdf): void
    {
        // Logo or branding
        $pdf->SetFont('Arial', 'B', 22);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 12, 'VERIFIED IDENTITY CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(3);
    }

    protected function renderTitle($pdf): void
    {
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'Certificate of Identity Verification', 0, 1, 'C');
        $pdf->Ln(5);
    }

    protected function renderVerificationBadge($pdf): void
    {
        // Green checkmark badge
        $pdf->SetFillColor(39, 174, 96);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 13);

        $pdf->Cell(0, 10, '[ IDENTITY VERIFIED ]', 1, 1, 'C', true);
        $pdf->Ln(5);
    }

    protected function renderPersonalInfo($pdf, $data): void
    {
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $this->addField($pdf, 'Full Name:', $data->fullName);
        $this->addField($pdf, 'Date of Birth:', $data->dateOfBirth);
        $this->addField($pdf, 'ID Type:', $data->idType);
        $this->addField($pdf, 'ID Number:', $data->idNumber);

        if ($data->address) {
            $this->addField($pdf, 'Address:', $data->address);
        }

        $pdf->Ln(5);
    }

    protected function addField($pdf, $label, $value): void
    {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(45, 6, $label, 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $value, 0, 1);
    }

    protected function renderImages($pdf, $data): void
    {
        $y = $pdf->GetY();
        $leftX = 20;
        $rightX = 120;
        $imageWidth = 70;
        $imageHeight = 50;

        // ID Card Photo (top left)
        if ($data->idImagePath && file_exists($data->idImagePath)) {
            $pdf->Image($data->idImagePath, $leftX, $y, $imageWidth, $imageHeight);
            
            // Label
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY($leftX, $y + $imageHeight + 2);
            $pdf->Cell($imageWidth, 5, 'ID Card', 0, 0, 'C');
        }

        // Selfie Photo (bottom left)
        if ($data->selfieImagePath && file_exists($data->selfieImagePath)) {
            $selfieY = $y + $imageHeight + 10;
            $pdf->Image($data->selfieImagePath, $leftX, $selfieY, $imageWidth, $imageHeight);
            
            // Label
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY($leftX, $selfieY + $imageHeight + 2);
            $pdf->Cell($imageWidth, 5, 'Selfie', 0, 0, 'C');
        }

        // QR Code (right side, in a box)
        $qrPath = $this->generateQRCode($data->verificationUrl);
        if ($qrPath && file_exists($qrPath)) {
            $qrSize = 60;
            $qrY = $y + 10;
            $boxWidth = 70;
            $boxHeight = 80;
            
            // Draw border around QR section
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect($rightX - 5, $qrY - 5, $boxWidth, $boxHeight);
            
            // Title above QR
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(44, 62, 80);
            $pdf->SetXY($rightX - 5, $qrY - 2);
            $pdf->Cell($boxWidth, 5, 'Online Verification', 0, 0, 'C');
            
            // QR Code
            $pdf->Image($qrPath, $rightX, $qrY + 5, $qrSize, $qrSize);
            
            // "Scan to Verify" label
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($rightX - 5, $qrY + $qrSize + 7);
            $pdf->Cell($boxWidth, 4, 'Scan with your phone', 0, 1, 'C');
            $pdf->SetXY($rightX - 5, $qrY + $qrSize + 11);
            $pdf->Cell($boxWidth, 4, 'camera to verify', 0, 0, 'C');
        }

        // Move cursor below all images
        $pdf->SetY($y + ($imageHeight * 2) + 20);
    }

    protected function renderFooter($pdf, $data): void
    {
        // Security Features Section
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 6, 'Security Features', 0, 1);
        $pdf->Ln(1);
        
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(60, 60, 60);
        $this->addSecurityFeature($pdf, 'Identity verified via government-issued ID');
        $this->addSecurityFeature($pdf, 'Document digitally signed with PKCS#7 certificate');
        $this->addSecurityFeature($pdf, 'Cryptographic hash (SHA-256) for integrity');
        $this->addSecurityFeature($pdf, 'Timestamp recorded on Bitcoin blockchain');
        $this->addSecurityFeature($pdf, 'Publicly verifiable via QR code or URL');
        $pdf->Ln(3);
        
        // Verification Info
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Verification Date: '.$data->verificationDate, 0, 1);
        $pdf->Cell(0, 5, 'Transaction ID: '.$data->transactionId, 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'Verify this certificate online:', 0, 1);
        $pdf->SetFont('Arial', 'U', 8);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 5, $data->verificationUrl, 0, 1);
        $pdf->Ln(2);

        // Footer branding
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, 'Powered by HyperVerge KYC', 0, 0, 'C');
    }
    
    protected function addSecurityFeature($pdf, $text): void
    {
        // Checkmark symbol (using a bullet point as alternative)
        $pdf->Cell(5, 4, chr(149), 0, 0); // Bullet point
        $pdf->Cell(0, 4, $text, 0, 1);
    }

    protected function generateQRCode(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            // Use our GenerateVerificationQRCode action
            $qrCode = GenerateVerificationQRCode::run($url, 200, 5);
            return $qrCode['file_path'];
        } catch (\Exception $e) {
            // Log error and return null (certificate will still generate without QR)
            error_log('[DefaultCertificateLayout] Failed to generate QR code: ' . $e->getMessage());
            return null;
        }
    }
}
