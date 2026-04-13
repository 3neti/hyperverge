# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

`lbhurtado/hyperverge` is a comprehensive Laravel package for HyperVerge KYC APIs with document signing, face verification, QR code verification, blockchain timestamping, and certificate generation. This is a **standalone package repository** designed for publication to Packagist.

**Package Name**: `lbhurtado/hyperverge` (published as `3neti/hyperverge`)  
**PHP Version**: 8.2+  
**Laravel Support**: 11.x, 12.x  
**License**: MIT

## Core Architecture

### Design Patterns

1. **Laravel Actions Pattern** (`lorisleiva/laravel-actions`)
   - All business logic is encapsulated in reusable Action classes
   - Actions can be used as controllers, jobs, listeners, or invokable classes
   - Located in `src/Actions/` organized by feature domain

2. **Type-Safe DTOs** (`spatie/laravel-data`)
   - All API requests and responses use Data Transfer Objects
   - Located in `src/Data/` with subdirectories for Requests, Responses, Modules
   - Provides automatic validation, type casting, and serialization

3. **Hexagonal Architecture (Ports & Adapters)**
   - Contracts define interfaces for external dependencies: `TileAllocator`, `VerificationUrlResolver`, `DocumentStoragePort`, `CredentialResolverInterface`
   - Default implementations provided but easily swappable via Laravel's container
   - Enables testing with mock implementations

4. **Event-Driven Architecture**
   - Events fired for key lifecycle actions: `KYCApproved`, `DocumentSigned`, `FaceEnrolled`, `FaceVerificationSucceeded/Failed`
   - Located in `src/Events/` organized by domain
   - Supports custom listeners in consuming applications

### Directory Structure

```
src/
├── Actions/           # Business logic using Laravel Actions pattern
│   ├── Certificate/   # Certificate generation (PDF with QR)
│   ├── Document/      # Document signing, QR watermarking, stamping
│   ├── FaceVerification/  # Face enrollment and verification
│   ├── LinkKYC/       # KYC session creation
│   ├── Results/       # KYC result processing and validation
│   ├── Signature/     # PKCS#7 PDF signing
│   └── Timestamp/     # Blockchain timestamping (OpenTimestamps)
├── Contracts/         # Interfaces for dependency injection
├── Data/             # DTOs (Spatie Laravel Data)
│   ├── Modules/      # KYC module response types
│   ├── Requests/     # API request DTOs
│   ├── Responses/    # API response DTOs
│   └── Validation/   # Validation result DTOs
├── Enums/            # Type-safe enumerations (SignatureMode, ApplicationStatus)
├── Events/           # Domain events
├── Exceptions/       # Custom exception hierarchy
├── Factories/        # Object creation (HypervergeClient, Modules)
├── Http/             # HTTP layer (Controllers, Webhooks)
├── Jobs/             # Background jobs (cleanup, async processing)
├── Services/         # Service layer (API clients, business services)
├── Support/          # Helper classes (HypervergeClient wrapper)
├── Traits/           # Reusable model traits (HasDocuments, HasFaceVerification)
└── Webhooks/         # Webhook handling (Laravel Webhook Client)
```

## Common Development Commands

### Testing

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest tests/Actions/
vendor/bin/pest tests/Integration/

# Run with coverage
vendor/bin/pest --coverage

# Run specific test file
vendor/bin/pest tests/Actions/GenerateVerificationQRCodeTest.php

# Run tests matching pattern
vendor/bin/pest --filter="QR"
```

**Test Framework**: PestPHP v3/v4  
**Test Location**: `tests/` (16 test files, 33+ tests)  
**Test Suites**: Actions, Integration, Unit

### Code Quality

```bash
# Check syntax (if PHP CS Fixer is added)
# Note: This package currently does not include linting tools

# Validate composer.json
composer validate

# Update dependencies
composer update

# Check for outdated packages
composer outdated
```

### Publishing to Packagist

```bash
# Tag a new version
git tag -a v1.0.0 -m "Release v1.0.0: Complete KYC features"
git push origin v1.0.0

# Submit to Packagist (first time only)
# Visit: https://packagist.org/packages/submit
# Repository URL: https://github.com/lbhurtado/hyperverge

# Auto-update via GitHub webhook (configure on Packagist)
```

### Local Development (in Host Application)

When developing this package alongside a Laravel application:

```bash
# In consuming application's composer.json:
# "repositories": [
#     {
#         "type": "path",
#         "url": "../hyperverge-standalone"
#     }
# ]

# Install from local path
composer require lbhurtado/hyperverge:@dev

# Publish config
php artisan vendor:publish --tag=hyperverge-config

# Publish migrations (if added)
php artisan vendor:publish --tag=hyperverge-migrations
php artisan migrate

# Clear and rebuild
composer dump-autoload
php artisan config:clear
```

## Key Components & Workflows

### 1. KYC Verification Flow

**Entry Points**: `SelfieLivenessService`, `FaceMatchService`, `LinkKYCService`  
**Actions**: `CreateKYCSession`, `FetchKYCResult`, `ProcessKYCData`, `ValidateKYCResult`  
**Validation**: Configurable thresholds in `config/hyperverge.php` under `validation.*`

```php
// Basic flow
$service = app(LinkKYCService::class);
$session = $service->createSession($callbackUrl, $metadata);
// User completes KYC at $session['link']
// Webhook received at /hyperverge/webhook
// Process with ResultsService::fetch($sessionId)
```

### 2. Document Signing with KYC

**Signature Concept**: Uses KYC-verified identity (ID card image) as the signature, not traditional PKI  
**Actions**: `MarkDocumentWithKYC` (orchestrator), `ProcessIdImageStamp`, `StampDocument`, `TrackDocument`  
**Multi-Signature**: Supports 3x3 grid (9 tiles) with two modes:
- **Proforma Mode**: Sequential allocation (1→2→3→...→9)
- **Roll Mode**: Random allocation with recycling when full

**Tile Positions**: Configurable in `config/hyperverge.php` under `document_signing.watermark.tile_positions`

```php
// Complete signing workflow
$result = MarkDocumentWithKYC::run(
    model: $campaign,              // Must use HasDocuments trait
    transactionId: $txnId,         // HyperVerge transaction ID
    documentPath: $pdfPath,        // PDF to sign
    tileNumber: 1,                 // Optional, auto-allocates if omitted
    metadata: ['name' => 'Juan']   // Display metadata
);
// Returns: ['stamp' => MediaItem, 'signed_document' => MediaItem, 'tile' => int]
```

**Storage**: Uses Spatie Media Library via `HasDocuments` trait or custom `DocumentStoragePort` implementation

### 3. Face Verification (Biometric Authentication)

**Trait**: `HasFaceVerification` - Add to User or any model  
**Requirements**: Model must implement `Spatie\MediaLibrary\HasMedia` interface  
**Actions**: `EnrollFace`, `VerifyFace`  
**Events**: `FaceEnrolled`, `FaceVerificationSucceeded`, `FaceVerificationFailed`

```php
// Enroll reference selfie
$user->enrollFace($selfieFile, checkLiveness: true);

// Verify during login/payment
$result = $user->verifyFace($selfieFile, context: ['action' => 'login']);
if ($result->verified) {
    Auth::login($user);
}
```

**Configuration**: `config/hyperverge.php` under `face_verification.*`

### 4. QR Code Verification

**Actions**: `GenerateVerificationQRCode`, `AddQRWatermarkToPDF`  
**Certificate Generation**: `GenerateVerificationCertificate` (includes QR automatically)

```php
// Generate QR
$qr = GenerateVerificationQRCode::run($url, size: 300, margin: 10);
// Returns: ['data_uri' => '...', 'file_path' => '...', 'url' => '...']

// Add to PDF
$watermarkedPdf = AddQRWatermarkToPDF::run($pdfPath, $qr['file_path']);
```

**Verification URL Format**: `https://yourapp.com/verify/{campaign_uuid}/{transaction_id}`  
**QR Settings**: `config/hyperverge.php` under `qr_code.*` and `document_signing.qr_watermark.*`

### 5. Blockchain Timestamping

**Actions**: `CreateBlockchainTimestamp`, `VerifyBlockchainTimestamp`  
**Provider**: OpenTimestamps (Bitcoin blockchain)  
**Configuration**: `config/timestamp.php`

## Important Conventions

### Credential Resolution

The package supports **multi-tenant credentials** via the `CredentialResolverInterface`:

- **Default**: Uses env variables (`HYPERVERGE_APP_ID`, `HYPERVERGE_APP_KEY`)
- **Custom**: Implement interface to resolve per-user/campaign/tenant
- **Binding**: In service provider, bind your implementation to the interface

```php
// Example: Per-campaign credentials
$this->app->bind(CredentialResolverInterface::class, CampaignCredentialResolver::class);
```

### Tile Allocation Strategy

When signing documents with multiple signers:

1. **Use HasDocuments trait** on model with `signature_mode`, `used_tiles`, `max_tiles` columns
2. **Proforma vs Roll**: Set via `SignatureMode::Proforma` or `SignatureMode::Roll` enum
3. **Custom allocation**: Implement `TileAllocator` interface for custom logic (e.g., VIP gets center tile)

### Error Handling

**Exception Hierarchy**:
- `HypervergeException` (base)
  - `LivelinessFailedException` - Selfie liveness check failed
  - `FaceMatchFailedException` - Face matching failed

**Best Practice**: Catch specific exceptions to handle different failure modes:

```php
try {
    $result = $service->verify($image);
} catch (LivelinessFailedException $e) {
    // Handle liveness failure specifically
    $quality = $e->getResponse()['result']['quality'];
} catch (HypervergeException $e) {
    // Handle generic API errors
    Log::error('HyperVerge Error', ['response' => $e->getResponse()]);
}
```

### Testing Strategy

**Mocking External APIs**:

```php
// Mock HyperVerge API responses
Http::fake([
    '*/v1/selfie/liveness' => Http::response([
        'result' => ['isLive' => true, 'quality' => ['blur' => false]],
    ]),
]);
```

**Integration Tests**: Located in `tests/Integration/` - test complete workflows end-to-end  
**Unit Tests**: Test individual Actions with mocked dependencies

### Environment Configuration

**Required**:
```env
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=your_app_id
HYPERVERGE_APP_KEY=your_app_key
```

**Optional Features**:
```env
HYPERVERGE_QR_ENABLED=true
HYPERVERGE_DOCUMENT_SIGNING_ENABLED=true
HYPERVERGE_FACE_VERIFICATION_ENABLED=true
HYPERVERGE_AUTO_SIGN_ON_APPROVAL=false  # Auto-sign on KYC approval
```

**Validation Thresholds**:
```env
HYPERVERGE_MIN_FACE_MATCH=0.85
HYPERVERGE_MIN_LIVENESS=0.8
HYPERVERGE_REQUIRE_LIVENESS=true
```

## Key Files to Understand

### Service Layer
- `src/Services/SelfieLivenessService.php` - Selfie liveness checks
- `src/Services/FaceMatchService.php` - Face matching
- `src/Services/LinkKYCService.php` - KYC session management
- `src/Services/ResultsService.php` - Fetch and parse KYC results

### Core Actions
- `src/Actions/Document/MarkDocumentWithKYC.php` - Main document signing orchestrator
- `src/Actions/FaceVerification/EnrollFace.php` - Enroll reference selfie
- `src/Actions/FaceVerification/VerifyFace.php` - Verify face against reference
- `src/Actions/Certificate/GenerateVerificationCertificate.php` - Generate PDF certificates

### Configuration
- `config/hyperverge.php` - Main configuration (API, validation, features)
- `config/signature.php` - PKCS#7 signing configuration
- `config/timestamp.php` - Blockchain timestamp settings

### Traits
- `src/Traits/HasDocuments.php` - Document management for models (tile allocation, signed docs)
- `src/Traits/HasFaceVerification.php` - Face verification for models (enroll, verify, stats)

## Dependencies

### Core
- `spatie/laravel-data` - Type-safe DTOs
- `lorisleiva/laravel-actions` - Action pattern implementation
- `guzzlehttp/guzzle` - HTTP client for API calls

### Document Processing
- `filippo-toso/pdf-watermarker` - PDF watermarking
- `setasign/fpdf` - PDF manipulation
- `tecnickcom/tcpdf` - PDF generation
- `phpseclib/phpseclib` - PKCS#7 signing

### Image Processing
- `intervention/image` - Image manipulation (requires GD or Imagick)
- `endroid/qr-code` - QR code generation

### Optional (for consuming application)
- `spatie/laravel-medialibrary` - Document storage (required if using `HasDocuments` trait)

## Development Notes

### When Adding New Features

1. **Create Action class** in appropriate subdirectory of `src/Actions/`
2. **Define DTOs** in `src/Data/` for inputs/outputs
3. **Fire events** at key lifecycle points
4. **Write tests** in corresponding `tests/` subdirectory
5. **Update configuration** if new settings needed
6. **Document in README** with usage examples

### When Modifying APIs

- Update DTOs to match new response structure
- Add backward-compatible defaults
- Update tests to cover new fields
- Consider versioning if breaking changes

### Before Publishing

1. Update `CHANGELOG.md` with changes
2. Ensure all tests pass: `vendor/bin/pest`
3. Validate composer.json: `composer validate`
4. Tag release: `git tag -a v1.x.x -m "Release notes"`
5. Push tag: `git push origin v1.x.x`
6. Packagist auto-updates via webhook

## Documentation Resources

- **README.md** - Main package documentation with usage examples
- **QUICKSTART.md** - Quick integration guide for host applications
- **DOCUMENT_SIGNING_README.md** - Deep dive on signature system
- **CHANGELOG.md** - Version history and release notes
- **docs/** - Additional documentation (implementation guides, testing strategy)

## Related Projects

This package is designed for use in:
- `pabahay` - Housing mortgage application (Laravel 12 + Inertia + Vue 3)
- `camr` - Main CAMR application
- Other Laravel applications requiring KYC verification

When working across projects, ensure package changes are tested in consuming applications before publishing.
