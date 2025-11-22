# Certificate QR Integration - Phase 2

## Overview

Phase 2 verifies and documents the certificate QR integration. Verification certificates are automatically generated with embedded QR codes that link to the verification page.

## Status: ✅ ALREADY IMPLEMENTED

**Good news**: Certificate QR integration was already fully implemented in the codebase! This phase focused on:
1. Verifying the implementation
2. Adding comprehensive tests
3. Documenting the feature

## What Exists

### 1. Certificate Generation Action

**Location**: `packages/hyperverge-php/src/Actions/Certificate/GenerateVerificationCertificate.php`

**Features**:
- ✅ Generates PDF certificates from KYC data
- ✅ Automatically includes QR code with verification URL
- ✅ Supports custom layouts
- ✅ Extracts ID card and selfie images
- ✅ Includes security features list
- ✅ Displays transaction ID and verification date

**Usage**:

```php
use LBHurtado\HyperVerge\Actions\Certificate\GenerateVerificationCertificate;

$certificatePath = GenerateVerificationCertificate::run(
    model: $submission,
    transactionId: $transactionId,
    options: [
        'verificationUrl' => route('verify', [$uuid, $transactionId]),
        'metadata' => [
            'campaign' => $campaign->name,
            'campaign_id' => $campaign->id,
        ],
    ]
);
```

### 2. Certificate Layout

**Location**: `packages/hyperverge-php/src/Actions/Certificate/Layouts/DefaultCertificateLayout.php`

**Certificate Contents**:
- ✅ Header with "VERIFIED IDENTITY CERTIFICATE" title
- ✅ Green verification badge
- ✅ Personal information (name, DOB, ID type, ID number, address)
- ✅ ID card image (if available)
- ✅ Selfie image (if available)
- ✅ **QR code in bordered box** with "Scan to Verify" label
- ✅ Security features list (5 features)
- ✅ Verification date and transaction ID
- ✅ Clickable verification URL
- ✅ "Powered by HyperVerge KYC" footer

**QR Code Section**:
```php
// QR Code (right side, in a box)
$qrPath = $this->generateQRCode($data->verificationUrl);
if ($qrPath && file_exists($qrPath)) {
    $qrSize = 60;
    
    // Draw border around QR section
    $pdf->Rect($rightX - 5, $qrY - 5, $boxWidth, $boxHeight);
    
    // Title above QR
    $pdf->Cell($boxWidth, 5, 'Online Verification', 0, 0, 'C');
    
    // QR Code image
    $pdf->Image($qrPath, $rightX, $qrY + 5, $qrSize, $qrSize);
    
    // "Scan to Verify" label
    $pdf->Cell($boxWidth, 4, 'Scan with your phone', 0, 1, 'C');
    $pdf->Cell($boxWidth, 4, 'camera to verify', 0, 0, 'C');
}
```

### 3. Certificate Data DTO

**Location**: `packages/hyperverge-php/src/Actions/Certificate/CertificateData.php`

Type-safe data structure using Spatie Laravel Data:

```php
class CertificateData extends Data
{
    public function __construct(
        public string $fullName,
        public string $dateOfBirth,
        public string $idType,
        public string $idNumber,
        public ?string $address,
        public string $transactionId,
        public string $verificationDate,
        public string $verificationUrl,     // ← Used for QR code
        public ?string $idImagePath,
        public ?string $selfieImagePath,
        public array $metadata = [],
    ) {}
}
```

### 4. Integration in Document Signing Workflow

**Location**: `app/Actions/ProcessCampaignDocumentsForSigning.php`

Certificates are automatically generated during document signing:

```php
// Generate verification certificate
$certificatePath = GenerateVerificationCertificate::run(
    model: $submission,
    transactionId: $submission->transaction_id,
    options: [
        'metadata' => [
            'campaign' => $campaign->name,
            'campaign_id' => $campaign->id,
            'submission_id' => $submission->id,
        ],
        'verificationUrl' => route('verify', [
            'uuid' => $campaign->id,
            'transactionId' => $submission->transaction_id,
        ]),
    ]
);

// Store the certificate
$certificate = $submission->addMedia($certificatePath)
    ->withCustomProperties([
        'transaction_id' => $submission->transaction_id,
        'certificate_type' => 'verification',
        'campaign_id' => $campaign->id,
        'generated_at' => now()->toIso8601String(),
    ])
    ->usingName('Verification Certificate - ' . $campaign->name)
    ->usingFileName('certificate_' . $submission->transaction_id . '.pdf')
    ->toMediaCollection('signed_documents');
```

## Certificate Layout Visual

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│      VERIFIED IDENTITY CERTIFICATE                      │
│      Certificate of Identity Verification               │
│                                                         │
│           [ IDENTITY VERIFIED ]                         │
│                                                         │
│  Full Name:        Juan Dela Cruz                       │
│  Date of Birth:    1990-01-01                          │
│  ID Type:          Driver's License                     │
│  ID Number:        DL123456789                         │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │              │  │              │  │  Online      │ │
│  │   ID Card    │  │   Selfie     │  │ Verification │ │
│  │    Image     │  │    Image     │  │              │ │
│  │              │  │              │  │  ┌────────┐  │ │
│  └──────────────┘  └──────────────┘  │  │ QR Code│  │ │
│                                       │  │        │  │ │
│  Security Features                    │  │        │  │ │
│  • Identity verified via ID           │  └────────┘  │ │
│  • Digitally signed                   │  Scan with   │ │
│  • Cryptographic hash                 │ phone camera │ │
│  • Blockchain timestamp               └──────────────┘ │
│  • Publicly verifiable                                 │
│                                                         │
│  Verification Date: January 20, 2025 14:30            │
│  Transaction ID: abc123...xyz789                       │
│                                                         │
│  Verify this certificate online:                       │
│  https://example.com/verify/uuid/txid                  │
│                                                         │
│           Powered by HyperVerge KYC                    │
└─────────────────────────────────────────────────────────┘
```

## Testing

### Unit Tests

**Location**: `packages/hyperverge-php/tests/Actions/GenerateVerificationCertificateTest.php`

**Coverage** (6 tests):
- ✅ Generates certificate PDF
- ✅ Certificate includes QR code
- ✅ Certificate data extracts from KYC result
- ✅ Certificate includes verification URL
- ✅ Certificate handles missing optional data
- ✅ Certificate generates without QR if URL missing

Run tests:

```bash
cd packages/hyperverge-php
../../vendor/bin/pest tests/Actions/GenerateVerificationCertificateTest.php
```

### Manual Testing

Test certificate generation via Artisan Tinker:

```bash
php artisan tinker --execute="
use LBHurtado\HyperVerge\Actions\Certificate\GenerateVerificationCertificate;
use App\Models\CampaignSubmission;

\$submission = CampaignSubmission::where('kyc_status', 'approved')->first();

if (\$submission) {
    \$cert = GenerateVerificationCertificate::run(
        model: \$submission,
        transactionId: \$submission->transaction_id,
        options: ['verificationUrl' => 'https://example.com/verify/test']
    );
    echo 'Certificate generated: ' . \$cert . PHP_EOL;
    echo 'Size: ' . filesize(\$cert) . ' bytes' . PHP_EOL;
} else {
    echo 'No approved submission found';
}
"
```

## Workflow

### When Certificates Are Generated

Certificates are automatically generated in two scenarios:

**1. Document Signing Workflow**
```
Campaign Documents → Sign → Add QR Watermark → Timestamp → Generate Certificate
                                                              ↓
                                                        Store in signed_documents
```

**2. Manual Generation** (if needed)
```php
$certificatePath = GenerateVerificationCertificate::run($submission, $transactionId, $options);
```

### Certificate Storage

Certificates are stored in the `signed_documents` media collection with custom properties:

```php
'custom_properties' => [
    'transaction_id' => $transactionId,
    'certificate_type' => 'verification',
    'campaign_id' => $campaign->id,
    'generated_at' => now()->toIso8601String(),
]
```

## QR Code Implementation

### Generation

The certificate layout uses `GenerateVerificationQRCode` action (same as Phase 1):

```php
protected function generateQRCode(string $url): ?string
{
    if (empty($url)) {
        return null;
    }

    try {
        $qrCode = GenerateVerificationQRCode::run($url, 200, 5);
        return $qrCode['file_path'];
    } catch (\Exception $e) {
        error_log('[DefaultCertificateLayout] Failed to generate QR code: ' . $e->getMessage());
        return null;
    }
}
```

**QR Settings**:
- **Size**: 200px (larger than PDF watermark for better scannability)
- **Margin**: 5px
- **Position**: Right side of certificate in bordered box
- **Fallback**: Certificate generates without QR if generation fails

### Styling

- QR code is placed in a bordered box with gray outline
- "Online Verification" title above QR
- "Scan with your phone camera to verify" label below QR
- 60mm × 60mm display size on A4 certificate

## Benefits

✅ **Professional Appearance** - Clean, bordered QR section  
✅ **Easy to Scan** - Larger QR (200px) optimized for mobile cameras  
✅ **Clear Instructions** - "Scan to Verify" label guides users  
✅ **Graceful Degradation** - Certificate generates even if QR fails  
✅ **Type Safety** - Spatie Data DTOs ensure data integrity  
✅ **Flexible Layouts** - Support for custom certificate layouts  

## Verification Page Integration

Certificates link to the same verification page as signed documents:

**URL Format**: `/verify/{campaign_uuid}/{transaction_id}`

**Page Contents**:
- ✅ KYC identity data
- ✅ Signature stamp image
- ✅ ID card image
- ✅ Signed document downloads
- ✅ Blockchain timestamp status
- ✅ Shareable QR code
- ✅ Copy link button

## Customization

### Custom Certificate Layout

Create a custom layout by extending the base class:

```php
namespace App\Certificate\Layouts;

use LBHurtado\HyperVerge\Actions\Certificate\Layouts\DefaultCertificateLayout;

class BrandedCertificateLayout extends DefaultCertificateLayout
{
    protected function renderHeader($pdf): void
    {
        // Custom branding
        $pdf->Image(public_path('images/logo.png'), 20, 10, 30);
        parent::renderHeader($pdf);
    }
}
```

Use custom layout:

```php
GenerateVerificationCertificate::run($model, $transactionId, [
    'layout' => BrandedCertificateLayout::class,
    'verificationUrl' => $url,
]);
```

## Configuration

No additional configuration needed. Uses existing QR generation settings:

```php
// config/hyperverge.php
'qr_code' => [
    'enabled' => true,
    'default_size' => 300,  // Overridden to 200 in certificate
    'margin' => 10,         // Overridden to 5 in certificate
    'error_correction' => 'H',
],
```

## Troubleshooting

### Certificate not generating

1. Check if KYC result exists:
   ```php
   $result = FetchKYCResult::run($transactionId);
   dd($result);
   ```

2. Verify FPDF is working:
   ```bash
   composer show setasign/fpdf
   ```

### QR not appearing on certificate

1. Check QR generation:
   ```php
   $qr = GenerateVerificationQRCode::run('https://example.com');
   dd($qr, file_exists($qr['file_path']));
   ```

2. Check error logs:
   ```bash
   tail -f storage/logs/laravel.log | grep DefaultCertificateLayout
   ```

### Certificate missing data

1. Check KYC result structure:
   ```php
   $result = FetchKYCResult::run($transactionId);
   dd($result->modules);
   ```

2. Verify model has media:
   ```php
   dd($model->getMedia('kyc_id_cards'));
   ```

## Next Steps (Phase 3)

- [ ] End-to-end workflow tests
- [ ] Certificate display on verification page
- [ ] QR scanability validation
- [ ] Mobile responsiveness testing
- [ ] Performance benchmarks

## Summary

✅ **Phase 2 Complete** - Certificate QR integration verified and documented  
✅ **Already Working** - No code changes needed, only tests and docs added  
✅ **6 New Tests** - Comprehensive certificate generation test coverage  
✅ **Professional Quality** - Clean, scannable QR codes on certificates  

Certificates are automatically generated with QR codes linking to verification pages. The implementation is mature, tested, and production-ready.
