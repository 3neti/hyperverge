# Robust KYC Data & Image Saving

This document explains the enhanced, production-ready actions for saving KYC verification data and images from HyperVerge.

## Overview

The package now includes **transaction-safe, validated, and error-resilient** actions for storing KYC data:

1. **`StoreKYCImagesWithValidation`** - Download and validate images with transaction safety
2. **`ProcessKYCDataWithValidation`** - Extract and validate data fields with transformation
3. **`SaveKYCDataWithTransaction`** - Atomic wrapper combining both (recommended)

## Why Use These Actions?

### Original Actions (Still Available)

```php
// Old way - no validation, no transaction safety
$imageUrls = ExtractKYCImages::run($transactionId);
StoreKYCImages::run($user, $imageUrls, $transactionId);
ProcessKYCData::run($user, $transactionId);
```

**Problems**:
- ❌ No image validation (corrupt files, wrong formats, huge sizes)
- ❌ No data validation (invalid emails, malformed dates)
- ❌ No transaction safety (partial failures leave inconsistent state)
- ❌ Silent failures (errors logged but not reported)
- ❌ No duplicate detection

### New Robust Actions

```php
// New way - validated, transaction-safe, atomic
$result = SaveKYCDataWithTransaction::run(
    $user, 
    $transactionId,
    includeAddress: true,
    skipDuplicateImages: true,
    overwriteExistingData: true,
    strictValidation: false
);

if ($result['success']) {
    echo "✅ Saved {$result['images']['stored_count']} images and " .
         count($result['data']['stored_inputs']) . " fields";
} else {
    echo "❌ Failed: {$result['error']}";
}
```

**Benefits**:
- ✅ **Image validation** - Format, size, corruption checks
- ✅ **Data validation** - Email, mobile, date format validation
- ✅ **Transaction safety** - All-or-nothing (rolls back on failure)
- ✅ **Duplicate detection** - Skip already downloaded images
- ✅ **Data transformation** - Normalize dates, phone numbers, names
- ✅ **Comprehensive errors** - Detailed error reporting
- ✅ **Atomic operations** - Both images and data saved together

---

## Actions in Detail

### 1. StoreKYCImagesWithValidation

Enhanced image storage with validation and transaction safety.

#### Features

- **Image format validation** - Uses GD to verify valid image (not corrupted)
- **MIME type validation** - Only allows: `image/jpeg`, `image/png`, `image/webp`
- **File size limits** - Max 10MB per image (configurable)
- **Auto-detection** - Detects actual file format (not hardcoded `.jpg`)
- **Duplicate detection** - Checks by original URL in custom properties
- **Transaction safety** - DB transaction with rollback on any failure
- **Retry logic** - 3 retries with 200ms delay (configurable)
- **Detailed logging** - Every step logged for debugging

#### Usage

```php
use LBHurtado\HyperVerge\Actions\Results\StoreKYCImagesWithValidation;

$result = StoreKYCImagesWithValidation::run(
    model: $campaignSubmission,
    imageUrls: $imageUrls,
    transactionId: $transactionId,
    skipDuplicates: true
);

// Check result
if ($result['success']) {
    echo "Stored: {$result['stored_count']}";
    echo "Skipped: " . count($result['skipped']);
    
    // Access stored media
    foreach ($result['stored_media'] as $key => $media) {
        echo "{$key}: {$media->file_name} ({$media->size} bytes)";
    }
} else {
    echo "Failed: " . $result['errors']['transaction'];
    
    // Check individual errors
    foreach ($result['errors'] as $imageKey => $error) {
        echo "Error downloading {$imageKey}: {$error}";
    }
}
```

#### Configuration

```env
# Max file size (bytes)
HYPERVERGE_IMAGE_MAX_SIZE=10485760

# Download timeout (seconds)
HYPERVERGE_IMAGE_TIMEOUT=30

# Retry attempts
HYPERVERGE_IMAGE_MAX_RETRIES=3

# Storage disk
HYPERVERGE_STORAGE_DISK=public
```

#### Result Structure

```php
[
    'success' => true,
    'stored_media' => [
        'id_card_full' => Media,
        'id_card_cropped' => Media,
        'selfie' => Media,
    ],
    'skipped' => [
        'id_card_full' => 'Already exists',
    ],
    'errors' => [],
    'stored_count' => 3,
]
```

---

### 2. ProcessKYCDataWithValidation

Enhanced data extraction with validation and transformation.

#### Features

- **Field validation** - Email format, mobile regex, date format
- **Data transformation** - Normalize dates (Y-m-d), phone numbers (E.164), names (title case)
- **Transaction safety** - DB transaction with rollback
- **Duplicate detection** - Option to skip existing values
- **Strict/lenient modes** - Fail-fast or store valid fields only
- **Comprehensive errors** - Per-field validation errors

#### Usage

```php
use LBHurtado\HyperVerge\Actions\Results\ProcessKYCDataWithValidation;

$result = ProcessKYCDataWithValidation::run(
    model: $campaignSubmission,
    transactionId: $transactionId,
    includeAddress: true,
    strict: false, // Store valid fields even if some fail
    overwriteExisting: true
);

if ($result['success']) {
    echo "Stored: " . implode(', ', $result['stored_inputs']);
    echo "Skipped: " . implode(', ', $result['skipped_inputs']);
    
    // Access via HasInputs trait
    echo $campaignSubmission->name; // Magic accessor
    echo $campaignSubmission->birth_date;
    echo $campaignSubmission->email;
} else {
    // Check validation errors
    foreach ($result['validation_errors'] as $field => $errors) {
        echo "{$field}: " . implode(', ', (array)$errors);
    }
}
```

#### Validation Rules

| Field | Rules | Transformation |
|-------|-------|----------------|
| **name** | required, string, min:2, max:255 | Title case |
| **email** | nullable, email, max:255 | Lowercase |
| **mobile** | nullable, regex:/^\+?[0-9]{10,15}$/ | E.164 format (+63...) |
| **birth_date** | nullable, date_format:Y-m-d | Normalize to Y-m-d |
| **address** | nullable, string, max:500 | Title case |
| **reference_code** | nullable, string, max:255 | As-is |

#### Transformations

```php
// Name
"JUAN DELA CRUZ" → "Juan Dela Cruz"

// Email
"User@Example.COM" → "user@example.com"

// Mobile
"09171234567" → "+639171234567"
"+63 (917) 123-4567" → "+639171234567"

// Birth Date
"15/03/1990" → "1990-03-15"
"03-15-1990" → "1990-03-15"
```

#### Result Structure

```php
[
    'success' => true,
    'stored_inputs' => ['name', 'birth_date', 'email', 'mobile'],
    'skipped_inputs' => [],
    'validation_errors' => [
        'email' => ['The email must be a valid email address.'],
    ],
    'transformation_warnings' => [],
]
```

---

### 3. SaveKYCDataWithTransaction (Recommended)

Atomic wrapper combining image and data storage.

#### Features

- **Single atomic transaction** - Both images and data saved together
- **All-or-nothing** - Rolls back everything on any failure
- **Comprehensive result** - Includes both image and data results
- **Event dispatch** - Fires `KYCDataSaved` event on success
- **Helper methods** - `getSummary()`, `hasWarnings()`, `getWarnings()`

#### Usage (Recommended)

```php
use LBHurtado\HyperVerge\Actions\Results\SaveKYCDataWithTransaction;

// Simple usage
$result = SaveKYCDataWithTransaction::run($campaignSubmission, $transactionId);

// With options
$result = SaveKYCDataWithTransaction::run(
    model: $campaignSubmission,
    transactionId: $transactionId,
    includeAddress: true,
    skipDuplicateImages: true,
    overwriteExistingData: true,
    strictValidation: false
);

// Check result
if ($result['success']) {
    echo SaveKYCDataWithTransaction::getSummary($result);
    // "✅ Successfully saved 3 images and 5 data fields for transaction abc123"
    
    if (SaveKYCDataWithTransaction::hasWarnings($result)) {
        $warnings = SaveKYCDataWithTransaction::getWarnings($result);
        foreach ($warnings as $warning) {
            echo "⚠️ {$warning}";
        }
    }
} else {
    echo "❌ Failed: {$result['error']}";
    
    // Detailed errors
    dd($result['images']['errors'], $result['data']['validation_errors']);
}
```

#### Result Structure

```php
[
    'success' => true,
    'transaction_id' => 'abc123',
    'model_type' => 'App\\Models\\CampaignSubmission',
    'model_id' => 42,
    'images' => [
        'success' => true,
        'stored_media' => [...],
        'skipped' => [...],
        'errors' => [],
        'stored_count' => 3,
    ],
    'data' => [
        'success' => true,
        'stored_inputs' => ['name', 'birth_date', 'email'],
        'skipped_inputs' => [],
        'validation_errors' => [],
    ],
    'error' => null,
    'started_at' => '2025-01-21T10:00:00Z',
    'completed_at' => '2025-01-21T10:00:15Z',
]
```

---

## Integration Example

### UpdateCampaignSubmissionStatus Action

The host app's `UpdateCampaignSubmissionStatus` action now uses the robust transaction-safe approach:

```php
// Fetch and validate KYC result
$result = FetchKYCResult::run($submission->transaction_id);
$validation = ValidateKYCResult::run($result);

if ($validation->valid) {
    // Use atomic transaction to save everything
    $saveResult = SaveKYCDataWithTransaction::run(
        model: $submission,
        transactionId: $submission->transaction_id,
        includeAddress: true,
        skipDuplicateImages: true,
        overwriteExistingData: true,
        strictValidation: false
    );
    
    if ($saveResult['success']) {
        $submission->update([
            'kyc_status' => 'approved',
            'completed_at' => now(),
        ]);
        
        return [
            'success' => true,
            'status' => 'approved',
            'message' => "Stored {$saveResult['images']['stored_count']} images and " .
                        count($saveResult['data']['stored_inputs']) . " fields",
            'warnings' => SaveKYCDataWithTransaction::getWarnings($saveResult),
        ];
    }
}
```

---

## Events

### KYCDataSaved

Dispatched by `SaveKYCDataWithTransaction` on successful atomic save.

```php
use LBHurtado\HyperVerge\Events\KYCDataSaved;

Event::listen(function (KYCDataSaved $event) {
    $model = $event->model; // CampaignSubmission
    $transactionId = $event->transactionId;
    $result = $event->result; // Full result array
    
    // Send notification
    $user = User::where('email', $model->email)->first();
    $user?->notify(new KYCApprovedNotification($model));
    
    // Update dashboard
    Cache::tags('kyc-stats')->flush();
});
```

---

## Error Handling

### Common Errors

#### Image Validation Failures

```php
// File too large
"File too large: 12.5MB (max: 10MB)"

// Invalid format
"Invalid MIME type: application/pdf (allowed: image/jpeg, image/png, image/webp)"

// Corrupted image
"Invalid image format or corrupted file: https://..."

// Download failure
"Failed to download image from https://...: HTTP 404"
```

#### Data Validation Failures

```php
[
    'email' => ['The email must be a valid email address.'],
    'mobile' => ['The mobile format is invalid.'],
    'birth_date' => ['The birth date does not match the format Y-m-d.'],
]
```

#### Transaction Failures

```php
[
    'error' => 'Image storage failed: File too large: 12.5MB (max: 10MB)',
    'images' => ['errors' => ['id_card_full' => '...']],
    'data' => ['validation_errors' => []],
]
```

---

## Testing

### With Mocks

```php
use Illuminate\Support\Facades\Http;

// Mock HyperVerge API
Http::fake([
    'ind.idv.hyperverge.co/v1/photo/results' => Http::response([...]),
    '*.s3.*.amazonaws.com/*' => Http::response(file_get_contents('tests/fixtures/id-card.jpg')),
]);

$result = SaveKYCDataWithTransaction::run($submission, $transactionId);
expect($result['success'])->toBeTrue();
```

### Test Cases

- ✅ Successful save (images + data)
- ✅ Transaction rollback on image failure
- ✅ Transaction rollback on data failure
- ✅ Duplicate image detection
- ✅ Data validation failures (strict/lenient)
- ✅ Image format validation (invalid MIME, corrupted)
- ✅ File size limits
- ✅ Transformation (dates, phones, emails)

---

## Configuration Reference

### Environment Variables

```env
# Image Storage
HYPERVERGE_IMAGE_MAX_SIZE=10485760  # 10MB
HYPERVERGE_IMAGE_TIMEOUT=30
HYPERVERGE_IMAGE_MAX_RETRIES=3
HYPERVERGE_STORAGE_DISK=public

# Validation
HYPERVERGE_STRICT_VALIDATION=false
HYPERVERGE_SKIP_DUPLICATE_IMAGES=true
HYPERVERGE_OVERWRITE_EXISTING_DATA=true
```

### Config File

`packages/hyperverge-php/config/hyperverge.php`

```php
'images' => [
    'max_size' => env('HYPERVERGE_IMAGE_MAX_SIZE', 10 * 1024 * 1024),
    'timeout' => env('HYPERVERGE_IMAGE_TIMEOUT', 30),
    'max_retries' => env('HYPERVERGE_IMAGE_MAX_RETRIES', 3),
],

'validation' => [
    'strict' => env('HYPERVERGE_STRICT_VALIDATION', false),
    'skip_duplicate_images' => env('HYPERVERGE_SKIP_DUPLICATE_IMAGES', true),
    'overwrite_existing_data' => env('HYPERVERGE_OVERWRITE_EXISTING_DATA', true),
],
```

---

## Migration Guide

### From Original Actions

**Before**:
```php
$imageUrls = ExtractKYCImages::run($transactionId);
StoreKYCImages::run($user, $imageUrls, $transactionId);
ProcessKYCData::run($user, $transactionId, includeAddress: true);
```

**After**:
```php
$result = SaveKYCDataWithTransaction::run(
    $user, 
    $transactionId,
    includeAddress: true
);

if (!$result['success']) {
    Log::error('KYC save failed', ['error' => $result['error']]);
}
```

### Backwards Compatibility

The original actions (`StoreKYCImages`, `ProcessKYCData`) are **still available** and unchanged. You can migrate gradually:

1. Use new actions in new code
2. Keep old code working as-is
3. Migrate existing usages when convenient

---

## Best Practices

### 1. Always Check Success

```php
$result = SaveKYCDataWithTransaction::run($model, $transactionId);

if (!$result['success']) {
    // Handle failure - everything rolled back
    return back()->withErrors(['kyc' => $result['error']]);
}
```

### 2. Handle Warnings

```php
if (SaveKYCDataWithTransaction::hasWarnings($result)) {
    $warnings = SaveKYCDataWithTransaction::getWarnings($result);
    Log::warning('KYC saved with warnings', ['warnings' => $warnings]);
    
    // Notify admin
    Mail::to(config('mail.admin'))->send(new KYCWarningsNotification($warnings));
}
```

### 3. Use Strict Mode in Critical Paths

```php
// For user registration - must have all data
$result = SaveKYCDataWithTransaction::run(
    $user, 
    $transactionId,
    strictValidation: true // Fail if any field invalid
);
```

### 4. Be Lenient for Campaigns

```php
// For public campaigns - store what we can
$result = SaveKYCDataWithTransaction::run(
    $submission, 
    $transactionId,
    strictValidation: false // Store valid fields only
);
```

### 5. Monitor Failed Saves

```php
if (!$result['success']) {
    // Report to monitoring service
    Sentry::captureMessage('KYC save failed', [
        'transaction_id' => $transactionId,
        'error' => $result['error'],
        'images_errors' => $result['images']['errors'] ?? [],
        'data_errors' => $result['data']['validation_errors'] ?? [],
    ]);
}
```

---

## Performance Considerations

### Image Downloads

- **Parallel downloads**: Not implemented (sequential for transaction safety)
- **Timeout**: 30s per image (configurable)
- **Retries**: 3 attempts with 200ms delay
- **Expected time**: ~5-10 seconds for 3 images

### Database Operations

- **Transactions**: Two nested transactions (outer for atomicity)
- **Media library**: Uses Spatie's efficient storage
- **Inputs**: Uses lbhurtado/laravel-model-input's optimized storage

### Optimization Tips

1. **Use queue jobs** for webhook processing
2. **Increase timeout** for slow networks: `HYPERVERGE_IMAGE_TIMEOUT=60`
3. **Reduce retries** for faster failures: `HYPERVERGE_IMAGE_MAX_RETRIES=1`
4. **Skip duplicates** to avoid re-downloading: `skipDuplicateImages: true`

---

## Troubleshooting

### Images Not Saving

**Check**:
1. GD extension enabled: `php -m | grep gd`
2. File permissions: `storage/app/public` writable
3. Disk configured: `config/filesystems.php`
4. S3 URLs accessible: Test in browser
5. Logs: `tail -f storage/logs/laravel.log | grep StoreKYCImages`

### Data Not Saving

**Check**:
1. HasInputs trait added: `use HasInputs` in model
2. InputType enum exists: `lbhurtado/laravel-model-input` installed
3. Database migration run: `php artisan migrate`
4. Validation failing: Check `$result['data']['validation_errors']`
5. Logs: `tail -f storage/logs/laravel.log | grep ProcessKYCData`

### Transaction Rollback

**Check**:
1. Database supports transactions (MySQL InnoDB, PostgreSQL)
2. Nested transactions: Only one outer transaction allowed
3. Logs: Look for `"Transaction failed - rolled back"`

---

## Support

For issues or questions:
- Package: `lbhurtado/hyperverge`
- GitHub: https://github.com/lbhurtado/hyperverge-php
- Email: lester@hurtado.ph
