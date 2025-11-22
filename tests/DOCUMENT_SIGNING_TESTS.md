# Document Signing Test Suite

## Overview

Comprehensive test suite for the document signing feature using Pest PHP.

## Test Categories

### Unit Tests (`tests/Unit/`)

#### DefaultTileAllocatorTest
- ✅ Returns tile 1 when no tiles used
- ✅ Returns next available tile
- ✅ Returns null when all tiles used
- ✅ Handles non-sequential used tiles
- ✅ Respects custom max tiles
- ✅ Returns correct position for each tile (1-9)
- ✅ Returns fallback position for invalid tile
- ✅ Resets tile allocation

#### SignatureModeTest
- ✅ Has proforma and roll cases
- ✅ Proforma is default
- ✅ isTemplate() / isRoll() methods work
- ✅ Returns correct labels
- ✅ Returns correct descriptions
- ✅ Can be created from string

#### SpatieDocumentStorageTest
- ✅ Throws exception when model doesn't use HasMedia
- ✅ Returns null/false for models without trait
- ✅ Validates Media object types
- ✅ Throws exceptions for invalid inputs

### Feature Tests (`tests/Actions/`)

#### ProcessIdImageStampTest
- ✅ Creates stamp image from ID card
- ✅ Creates stamp without logo
- ✅ Handles empty metadata
- ✅ Creates unique file names
- ✅ Verifies output dimensions (1500x800px)
- ✅ Validates PNG format

## Running Tests

### All Tests
```bash
cd packages/hyperverge-php
composer test
```

Or using Pest directly:
```bash
./vendor/bin/pest
```

### Specific Test File
```bash
./vendor/bin/pest tests/Unit/DefaultTileAllocatorTest.php
```

### With Coverage
```bash
./vendor/bin/pest --coverage
```

### Watch Mode
```bash
./vendor/bin/pest --watch
```

## Test Requirements

### Fixtures Needed
Create `tests/fixtures/qr-code.png` - A simple 200x200px QR code image for testing.

```bash
# Generate test QR code using ImageMagick
convert -size 200x200 xc:white \
    -fill black \
    -draw "rectangle 50,50 150,150" \
    packages/hyperverge-php/tests/fixtures/qr-code.png
```

### Dependencies
All testing dependencies are already in `composer.json`:
- orchestra/testbench
- pestphp/pest
- pestphp/pest-plugin-laravel

## Test Coverage Goals

- **Unit Tests**: 100% coverage of services, contracts, enums
- **Feature Tests**: 90%+ coverage of actions
- **Integration Tests**: Key workflows end-to-end

## What's Tested

### ✅ Completed
1. Tile allocation logic
2. Signature mode enum behavior
3. Document storage contract validation
4. Stamp image generation

### 🚧 In Progress
5. StampDocument action (PDF watermarking)
6. MarkDocumentWithKYC orchestrator
7. TrackDocument action
8. HasDocuments trait
9. Full end-to-end workflow

### 📋 Planned
10. Contract implementations with real Spatie models
11. Event dispatching verification
12. Error handling edge cases
13. Performance benchmarks

## Notes

### Mocking HyperVerge API
For `MarkDocumentWithKYC` tests, we'll need to:
- Mock HTTP responses from HyperVerge API
- Use fixture KYC result data
- Stub image downloads

### PDF Generation
For `StampDocument` tests:
- Require test PDF files in fixtures
- Use pdf-watermarker library
- Validate watermark positioning

### Cleanup
Tests automatically clean up:
- Temporary stamp images
- Temporary signed documents
- Test media files

All temp files are created in `tmp/document-signing/` and removed after each test.
