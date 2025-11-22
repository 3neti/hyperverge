# QR Watermark Implementation - Phase 1

## Overview

Phase 1 adds QR code watermarks to signed PDF documents. Every signed PDF now includes a small QR code (typically in the bottom-right corner) that can be scanned to verify the document authenticity.

## What Was Implemented

### 1. New Action: `AddQRWatermarkToPDF`

**Location**: `packages/hyperverge-php/src/Actions/Document/AddQRWatermarkToPDF.php`

**Purpose**: Adds a QR code watermark to a signed PDF document.

**Features**:
- ✅ Configurable position (top-left, top-center, top-right, etc.)
- ✅ Configurable size (pixels)
- ✅ Configurable opacity (0-100%)
- ✅ Page selection (last page, all pages, specific page)
- ✅ Automatic QR code resizing
- ✅ Can be disabled via config
- ✅ Respects existing configuration
- ✅ Generates unique output files

**Usage**:

```php
use LBHurtado\HyperVerge\Actions\Document\AddQRWatermarkToPDF;
use LBHurtado\HyperVerge\Actions\Document\GenerateVerificationQRCode;

// Generate QR code
$qrData = GenerateVerificationQRCode::run('https://example.com/verify/uuid/txid');

// Add to PDF (uses config defaults)
$watermarkedPdf = AddQRWatermarkToPDF::run($signedPdfPath, $qrData['file_path']);

// Custom options
$watermarkedPdf = AddQRWatermarkToPDF::run(
    pdfPath: $signedPdfPath,
    qrCodePath: $qrData['file_path'],
    page: -1,              // Last page (default), 0 = all pages, 1+ = specific page
    position: 'bottom-right', // Position on page
    size: 100,             // QR size in pixels
    opacity: 100           // Opacity percentage
);
```

### 2. Enhanced Workflow: `MarkDocumentWithKYC`

**Location**: `packages/hyperverge-php/src/Actions/Document/MarkDocumentWithKYC.php`

**Changes**:
- ✅ Now generates QR code with both data URI and file path
- ✅ Automatically adds QR watermark to signed PDF
- ✅ Stores QR watermarked version in media library
- ✅ Adds verification URL to custom properties
- ✅ Properly cleans up all temp files (ID image, stamp, QR code, both PDFs)

**New Custom Properties**:
```php
[
    'transaction_id' => $transactionId,
    'tile' => $tile,
    'signed_at' => now()->toIso8601String(),
    'qr_watermarked' => true,              // NEW
    'verification_url' => $verificationUrl, // NEW
]
```

### 3. Configuration

**Location**: `packages/hyperverge-php/config/hyperverge.php`

Existing `qr_watermark` configuration is now actively used:

```php
'qr_watermark' => [
    'enabled' => true,              // Enable/disable QR watermarking
    'position' => 'bottom-right',   // QR position on PDF
    'size' => 100,                  // QR size in pixels (about 1 inch at 300 DPI)
    'page' => -1,                   // Last page (-1), all pages (0), or specific page (1+)
    'opacity' => 100,               // Opacity (0-100%)
],
```

## Testing

### Manual Testing

Test the action via Artisan Tinker:

```bash
php artisan tinker --execute="
\$url = 'https://example.com/verify/test';
\$qr = LBHurtado\HyperVerge\Actions\Document\GenerateVerificationQRCode::run(\$url);
echo 'QR Generated: ' . (file_exists(\$qr['file_path']) ? 'YES' : 'NO');
"
```

### Unit Tests

**Location**: `packages/hyperverge-php/tests/Actions/AddQRWatermarkToPDFTest.php`

**Coverage**:
- ✅ Adds QR watermark to PDF
- ✅ Applies watermark to last page by default
- ✅ Applies watermark to all pages when specified
- ✅ Applies watermark to specific page
- ✅ Supports different positions (9 positions tested)
- ✅ Supports custom QR size
- ✅ Supports custom opacity
- ✅ Respects disabled config
- ✅ Uses config defaults
- ✅ Creates unique output files
- ✅ Prepares QR code with different sizes

Run tests:

```bash
# From package directory
cd packages/hyperverge-php
../../vendor/bin/pest tests/Actions/AddQRWatermarkToPDFTest.php

# From host app (if tests are discovered)
vendor/bin/pest --filter=AddQRWatermarkToPDF
```

### Integration Tests

QR-related tests in host app:

```bash
vendor/bin/pest --filter="QR"
```

Expected passing tests:
- ✓ QR Code Generation → it generates valid QR code for verification URL
- ✓ QR Code Generation → it generates QR code with white background and black border
- ✓ Campaign Admin Endpoints → GET /campaigns/{uuid}/qrcode requires authentication
- ✓ Campaign Admin Endpoints → GET /campaigns/{uuid}/qrcode returns PNG image
- ✓ Campaign Admin Routes → it generates QR code image

## Workflow

### Before

1. Generate QR code
2. Create signature stamp (with QR in stamp)
3. Stamp PDF with signature
4. Store signed PDF

### After (Phase 1)

1. Generate QR code (with both data URI and file path)
2. Create signature stamp (with QR in stamp)
3. Stamp PDF with signature
4. **[NEW]** Add QR watermark to signed PDF
5. Store QR watermarked PDF
6. Clean up all temp files

## Visual Result

### Signature Stamp
The signature stamp (ID card + metadata + timestamp) already includes a QR code in the bottom-left corner.

### PDF Watermark
The final signed PDF now **also** has a separate QR code watermark in the bottom-right corner (or configured position) of the last page (or all pages).

This means:
- **Stamp QR** → Part of the signature visual, embedded in stamp image
- **PDF QR** → Separate watermark on the PDF page itself

Both QR codes link to the same verification URL.

## Benefits

✅ **Easy Verification**: Users can scan the QR code with any camera app  
✅ **Redundancy**: QR in both stamp and PDF watermark  
✅ **Flexible Positioning**: Config-driven position, size, opacity  
✅ **Performance**: QR generation happens once, reused for both stamp and watermark  
✅ **Clean Architecture**: Separate action for clear responsibility  
✅ **Configurable**: Can be disabled or customized per deployment  

## Next Steps (Phase 2+)

- [ ] Certificate QR integration
- [ ] End-to-end workflow tests
- [ ] QR scanability validation tests
- [ ] Mobile responsiveness testing
- [ ] Performance benchmarks
- [ ] Documentation with examples
- [ ] Troubleshooting guide

## Configuration Examples

### Disable QR Watermark

```env
# .env
HYPERVERGE_QR_WATERMARK_ENABLED=false
```

Or in config:

```php
'qr_watermark' => [
    'enabled' => false,
],
```

### QR on All Pages

```php
'qr_watermark' => [
    'page' => 0, // All pages
],
```

### Custom Position and Size

```php
'qr_watermark' => [
    'position' => 'top-right',
    'size' => 150,
    'opacity' => 80,
],
```

### QR on First Page Only

```php
'qr_watermark' => [
    'page' => 1, // First page
],
```

## API Reference

### AddQRWatermarkToPDF::run()

```php
/**
 * @param string $pdfPath Absolute path to signed PDF
 * @param string $qrCodePath Absolute path to QR code image
 * @param int|null $page Page number to watermark (null = last page, 0 = all pages, 1+ = specific page)
 * @param string|null $position Position constant
 * @param int|null $size QR code size in pixels
 * @param int|null $opacity Opacity (0-100)
 * @return string Absolute path to watermarked PDF
 */
public static function run(
    string $pdfPath,
    string $qrCodePath,
    ?int $page = null,
    ?string $position = null,
    ?int $size = null,
    ?int $opacity = null
): string
```

### Position Options

- `top-left`, `top-center`, `top-right`
- `middle-left`, `middle-center`, `middle-right`
- `bottom-left`, `bottom-center`, `bottom-right`

## Troubleshooting

### QR not appearing on PDF

1. Check if QR watermarking is enabled:
   ```php
   config('hyperverge.document_signing.qr_watermark.enabled')
   ```

2. Check QR file exists:
   ```php
   $qrData = GenerateVerificationQRCode::run($url);
   dd(file_exists($qrData['file_path']));
   ```

3. Check PDF is valid:
   ```bash
   pdfinfo signed_document.pdf
   ```

### Performance Issues

- QR generation is cached during signing workflow
- Temp files are cleaned up immediately after storage
- Consider using queue for bulk document signing

### QR too small/large

Adjust size in config:
```php
'qr_watermark' => [
    'size' => 150, // Increase for larger QR
],
```

At 300 DPI (typical PDF):
- 100px ≈ 0.33 inches (8.5mm)
- 150px ≈ 0.5 inches (12.7mm)
- 200px ≈ 0.67 inches (17mm)
