# Document Signing Package

Electronic document signing using KYC verification as the signature mechanism.

## Overview

This package adds electronic document signing capabilities to the HyperVerge KYC integration. Instead of traditional PKI-based signatures, it uses **verified KYC identity data as the signature**, creating tamper-evident watermarks from ID card images.

## Core Concept

**Signature = Verified Identity**
- No private keys, certificates, or PKI infrastructure needed
- KYC verification provides the authentication
- ID card image + metadata becomes the "signature"
- Multiple signatures supported via tile-based positioning (3x3 grid)

## Features

✅ **KYC-based signatures** - Uses verified identity as signature  
✅ **Visual stamps** - 1500x800px composite with ID image, logo, timestamp, QR code  
✅ **PDF watermarking** - Applies stamps to PDFs with configurable positioning  
✅ **Multi-signature support** - 9-tile grid for multiple signers  
✅ **Document tracking** - QR codes for verification  
✅ **Two signature modes**:
- **Proforma Mode**: Sequential allocation (tile 1→2→3...)
- **Roll Mode**: Random allocation with recycling

## Architecture

### Core Components

1. **Actions** (Laravel Actions pattern)
   - `ProcessIdImageStamp` - Creates signature stamp from KYC data
   - `StampDocument` - Applies stamp to PDF
   - `MarkDocumentWithKYC` - Orchestrates full signing flow
   - `TrackDocument` - Adds verification QR code

2. **Contracts** (Dependency Injection)
   - `TileAllocator` - Manages signature tile allocation
   - `VerificationUrlResolver` - Generates verification URLs
   - `DocumentStoragePort` - Adapter for document storage

3. **Services** (Default Implementations)
   - `DefaultTileAllocator` - Sequential tile allocation
   - `DefaultVerificationUrlResolver` - Route-based URL generation
   - `SpatieDocumentStorage` - Spatie Media Library adapter

4. **Traits**
   - `HasDocuments` - Document management methods for models

5. **Events**
   - `DocumentSigned` - Fired after successful signing

## Installation

### Package Setup

Already included in `lbhurtado/hyperverge` package. No additional installation needed.

### Publish Migration

```bash
php artisan vendor:publish --tag=hyperverge-migrations
php artisan migrate
```

This adds to `campaigns` table:
- `signature_mode` (enum: proforma/roll)
- `used_tiles` (json array)
- `max_tiles` (int, default: 9)

### Configuration

Published in `config/hyperverge.php` under `document_signing` section:

```php
'document_signing' => [
    'enabled' => true,
    'auto_sign_on_approval' => false,
    
    'stamp' => [
        'width' => 1500,
        'height' => 800,
        'logo' => [...],      // Logo overlay
        'timestamp' => [...],  // Timestamp banner
        'metadata' => [...],   // Name, email display
        'qr_code' => [...],    // Verification QR
    ],
    
    'watermark' => [
        'resolution' => 300,
        'tile_positions' => [...], // 3x3 grid
    ],
    
    'tiles' => [
        'max' => 9,
        'columns' => 3,
        'rows' => 3,
    ],
],
```

## Usage

### Basic Document Signing

```php
use LBHurtado\HyperVerge\Actions\Document\MarkDocumentWithKYC;

// Sign a document with KYC verification
$result = MarkDocumentWithKYC::run(
    model: $campaign,              // Model with HasDocuments trait
    transactionId: 'user_123_abc', // HyperVerge transaction ID
    documentPath: '/path/to/document.pdf',
    tileNumber: 1,                 // Optional, auto-allocates if not provided
    metadata: [                    // Optional display metadata
        'name' => 'Juan dela Cruz',
        'email' => 'juan@example.com',
        'mobile' => '+639171234567',
    ]
);

// Returns:
// [
//     'stamp' => MediaItem,          // Signature stamp image
//     'signed_document' => MediaItem, // Watermarked PDF
//     'tile' => 1,                   // Assigned tile number
// ]
```

### Using with Campaign Models

```php
use App\Models\Campaign;
use LBHurtado\HyperVerge\Traits\HasDocuments;

class Campaign extends Model
{
    use HasDocuments; // Adds document management methods
    
    protected $casts = [
        'signature_mode' => SignatureMode::class,
        'used_tiles' => 'array',
    ];
}

// Auto-sign when KYC approved
$campaign = Campaign::find($uuid);
$submission = $campaign->submissions()->where('transaction_id', $transactionId)->first();

MarkDocumentWithKYC::run(
    model: $campaign,
    transactionId: $submission->transaction_id,
    documentPath: storage_path('app/contracts/template.pdf')
);

// Access signed documents
$stamps = $campaign->getMedia('signature_marks');
$signedDocs = $campaign->getMedia('signed_documents');

// Check tile availability
if ($campaign->canAllocateTile()) {
    $nextTile = $campaign->allocateNextTile();
}
```

### Signature Modes

```php
use LBHurtado\HyperVerge\Enums\SignatureMode;

// Proforma Mode (Sequential)
$campaign->signature_mode = SignatureMode::Proforma;
// Allocates: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9

// Roll Mode (Random with recycling)
$campaign->signature_mode = SignatureMode::Roll;
// Allocates: 3 → 7 → 1 → 5 → ... (shuffled order)
// When full, recycles from tile 1 again
```

### Custom Tile Allocation

```php
use LBHurtado\HyperVerge\Contracts\TileAllocator;

class PriorityTileAllocator implements TileAllocator
{
    public function nextTile(array $usedTiles = [], int $maxTiles = 9): ?int
    {
        // Custom logic: VIP gets tile 5 (center), others get edges
        $vipTile = 5;
        if (!in_array($vipTile, $usedTiles)) {
            return $vipTile;
        }
        
        // Fallback to sequential
        for ($tile = 1; $tile <= $maxTiles; $tile++) {
            if (!in_array($tile, $usedTiles)) {
                return $tile;
            }
        }
        
        return null;
    }
    
    // ... implement other methods
}

// Register in AppServiceProvider
$this->app->bind(TileAllocator::class, PriorityTileAllocator::class);
```

### Listening to Events

```php
use LBHurtado\HyperVerge\Events\DocumentSigned;

Event::listen(function (DocumentSigned $event) {
    Log::info('Document signed', [
        'model' => get_class($event->model),
        'transaction_id' => $event->transactionId,
        'tile' => $event->tile,
        'stamp_url' => $event->stamp->getUrl(),
        'signed_doc_url' => $event->signedDocument->getUrl(),
    ]);
    
    // Notify signers
    NotifySignersJob::dispatch($event->model);
});
```

## Testing

### Run Tests

```bash
cd packages/hyperverge-php
composer test
```

### Test Coverage

**39 tests, 111 assertions**

- ✅ DefaultTileAllocator (8 tests)
- ✅ SignatureMode enum (7 tests)
- ✅ SpatieDocumentStorage (6 tests)
- ✅ ProcessIdImageStamp (4 tests)
- ✅ StampDocument (3 tests)
- ✅ MarkDocumentWithKYC (6 tests)
- ✅ TrackDocument (2 tests)
- ✅ HasDocuments trait (3 tests)

See `tests/DOCUMENT_SIGNING_TESTS.md` for detailed test documentation.

## Dependencies

- `filippo-toso/pdf-watermarker` ^1.0 - PDF watermarking
- `intervention/image` ^2.7 - Image manipulation
- `simplesoftwareio/simple-qrcode` ^4.2 - QR code generation
- `spatie/laravel-medialibrary` ^11.0 - Document storage

## API Reference

### Actions

#### ProcessIdImageStamp

Creates a signature stamp from KYC ID card image.

```php
ProcessIdImageStamp::run(
    idImagePath: string,      // Path to ID card image
    metadata: array,          // ['name', 'email', 'mobile']
    verificationUrl: string   // URL for QR code
): string // Returns path to generated stamp
```

#### StampDocument

Applies watermark stamp to PDF.

```php
StampDocument::run(
    documentPath: string,  // Path to source PDF
    stampPath: string,     // Path to stamp image
    position: array,       // ['vertical' => ..., 'horizontal' => ..., 'offsetX' => ..., 'offsetY' => ...]
    resolution: int        // DPI (default: 300)
): string // Returns path to watermarked PDF
```

#### MarkDocumentWithKYC

Full orchestration of document signing.

```php
MarkDocumentWithKYC::run(
    model: Model,           // Model with HasDocuments trait
    transactionId: string,  // HyperVerge transaction ID
    documentPath: string,   // Path to document to sign
    tileNumber: ?int,       // Optional tile (auto-allocates if null)
    metadata: ?array        // Optional display metadata
): array // ['stamp' => MediaItem, 'signed_document' => MediaItem, 'tile' => int]
```

#### TrackDocument

Adds verification QR code to document.

```php
TrackDocument::run(
    model: Model,
    documentPath: string,
    verificationUrl: string,
    qrCodeSize: int,
    position: string,
    page: int
): MediaItem
```

### Contracts

#### TileAllocator

```php
interface TileAllocator
{
    public function nextTile(array $usedTiles = [], int $maxTiles = 9): ?int;
    public function getTilePosition(int $tile): array;
    public function reset(): array;
}
```

#### VerificationUrlResolver

```php
interface VerificationUrlResolver
{
    public function resolve(Model $model, string $transactionId): string;
}
```

#### DocumentStoragePort

```php
interface DocumentStoragePort
{
    public function store(Model $model, string $filePath, string $collection, array $customProperties = []): mixed;
    public function getUrl(mixed $media): string;
}
```

### Traits

#### HasDocuments

```php
trait HasDocuments
{
    public function canAllocateTile(): bool;
    public function allocateNextTile(): ?int;
    public function markTileAsUsed(int $tile): void;
    public function getTilePosition(int $tile): array;
    public function getUsedTiles(): array;
    public function resetTiles(): void;
}
```

## Next Steps

### Host App Integration (Phase 4)

1. **Update Campaign model**
   ```php
   use LBHurtado\HyperVerge\Traits\HasDocuments;
   
   class Campaign extends Model
   {
       use HasDocuments;
       
       protected $casts = [
           'signature_mode' => SignatureMode::class,
           'used_tiles' => 'array',
       ];
   }
   ```

2. **Create admin UI**
   - Campaign document upload
   - Signature mode selection
   - View signed documents
   - Download signed PDFs

3. **Add webhook auto-signing**
   ```php
   // In ProcessHypervergeWebhook job
   if ($campaign->config['auto_sign'] ?? false) {
       MarkDocumentWithKYC::dispatch($campaign, $transactionId, $documentPath);
   }
   ```

4. **Create public verification page**
   - Route: `/verify/{campaign}/{transaction}`
   - Show KYC data, stamp preview, document download

## License

MIT License - Part of lbhurtado/hyperverge package
