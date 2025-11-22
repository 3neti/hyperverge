# Face Verification Guide

Comprehensive guide to implementing face verification in your Laravel application using the HyperVerge package.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [Setup](#setup)
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
- [Use Cases](#use-cases)
- [Configuration](#configuration)
- [Security Best Practices](#security-best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

Face verification enables biometric authentication using facial recognition. Unlike full KYC verification which validates identity with government IDs, face verification is designed for ongoing authentication after initial identity verification.

**What it does:**
- Store a reference selfie for a user/model
- Verify subsequent selfies against the stored reference
- Perform liveness checks to prevent spoofing
- Maintain audit trail of verification attempts

**Common use cases:**
- Login by face
- Payment authorization
- Document acknowledgment/signing
- Access control
- Transaction verification

## Key Concepts

### Three Workflows

| Workflow | Purpose | When to Use |
|----------|---------|-------------|
| **KYC** | Full identity verification with government ID | Initial onboarding, compliance requirements |
| **Face Verification** | Authenticate using stored reference selfie | Login, payments, ongoing authentication |
| **Dedupe** (Optional) | Detect duplicate identities across system | Fraud prevention, duplicate account detection |

### Storage Strategy

**Reference Selfie** (`face_reference_selfies`)
- Single baseline image per model
- Used for all future verifications
- Can be updated securely
- Stored locally in your app

**Verification Attempts** (`face_verification_attempts`)
- Audit trail of all verification attempts
- Includes result, confidence scores, context
- Auto-cleaned based on retention policy (default: 30 days)
- Optional - can be disabled in config

**Archived References** (`face_reference_selfies_archive`)
- Previous reference selfies when updated
- Maintains history for compliance
- Not used for verification

### Verification Flow

```
┌─────────────┐
│  User       │
│  captures   │
│  selfie     │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│  1. Liveness Check          │ (Optional)
│     - Verify selfie is live │
│     - Prevent photo/video   │
│       spoofing              │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│  2. Face Matching           │
│     - Compare with          │
│       reference selfie      │
│     - Calculate confidence  │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│  3. Store Attempt           │ (Optional)
│     - Save attempt details  │
│     - Include context       │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│  4. Return Result           │
│     - verified: bool        │
│     - matchConfidence       │
│     - failureReason         │
└─────────────────────────────┘
```

## Setup

### 1. Add Trait to Model

```php
use LBHurtado\HyperVerge\Traits\HasFaceVerification;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Model implements HasMedia
{
    use InteractsWithMedia, HasFaceVerification;
    
    public function registerMediaCollections(): void
    {
        // Reference selfie (single file per user)
        $this->addMediaCollection('face_reference_selfies')
            ->singleFile()
            ->useDisk('public'); // or 'private' for better security
        
        // Verification attempts (audit trail)
        $this->addMediaCollection('face_verification_attempts')
            ->useDisk('public');
        
        // Archived references (history)
        $this->addMediaCollection('face_reference_selfies_archive')
            ->useDisk('public');
    }
}
```

### 2. Configure Environment

Add to `.env`:

```env
# Face Verification Settings
HYPERVERGE_FACE_VERIFICATION_ENABLED=true
HYPERVERGE_FACE_LIVENESS_REQUIRED=true
HYPERVERGE_FACE_MIN_LIVENESS=0.8
HYPERVERGE_FACE_MIN_MATCH=0.85
HYPERVERGE_STORE_FACE_ATTEMPTS=true
HYPERVERGE_FACE_ATTEMPTS_RETENTION=30
```

### 3. Schedule Cleanup (Optional)

In `app/Console/Kernel.php`:

```php
use LBHurtado\HyperVerge\Jobs\CleanupFaceVerificationAttempts;

protected function schedule(Schedule $schedule)
{
    // Clean up old verification attempts weekly
    $schedule->job(new CleanupFaceVerificationAttempts)->weekly();
}
```

## Basic Usage

### Enrollment (Store Reference Selfie)

```php
// During user registration or profile setup
$user->enrollFace(
    selfie: $request->file('selfie'),
    checkLiveness: true,
    metadata: [
        'enrolled_at' => now(),
        'ip_address' => $request->ip(),
        'device' => $request->userAgent(),
    ]
);
```

**Via Action:**

```php
use LBHurtado\HyperVerge\Actions\FaceVerification\EnrollFace;

$media = EnrollFace::run(
    model: $user,
    selfie: $request->file('selfie'),
    checkLiveness: true,
    metadata: ['source' => 'mobile_app']
);
```

**Via Artisan:**

```bash
php artisan hyperverge:enroll-face "App\Models\User" 1 /path/to/selfie.jpg
php artisan hyperverge:enroll-face "App\Models\User" 1 /path/to/selfie.jpg --no-liveness
```

### Verification (Check Against Reference)

```php
$result = $user->verifyFace(
    selfie: $request->file('selfie'),
    checkLiveness: true,
    storeAttempt: true,
    context: [
        'action' => 'login',
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]
);

if ($result->verified) {
    // Success! User verified
    Auth::login($user);
} else {
    // Failed - check reason
    Log::warning('Face verification failed', [
        'user_id' => $user->id,
        'reason' => $result->failureReason,
        'confidence' => $result->matchConfidence,
    ]);
}
```

**Result Object:**

```php
$result->verified;           // bool - Overall result
$result->livenessCheck;      // bool - Liveness passed
$result->livenessScore;      // float|null - Liveness score
$result->faceMatch;          // bool - Face match passed
$result->matchConfidence;    // float - Match confidence (0.0-1.0)
$result->quality;            // array - Quality metrics
$result->timestamp;          // string - ISO 8601 timestamp
$result->failureReason;      // string|null - Reason if failed
```

### Helper Methods

```php
// Check if user has enrolled
if ($user->hasReferenceSelfie()) {
    // Can verify
}

// Get reference selfie
$media = $user->getReferenceSelfie();

// Get verification statistics
$stats = $user->getFaceVerificationStats();
/*
[
    'total_attempts' => 42,
    'successful' => 40,
    'failed' => 2,
    'success_rate' => 95.24,
    'last_attempt_at' => Carbon instance,
    'last_successful_at' => Carbon instance,
    'has_reference_selfie' => true,
    'reference_enrolled_at' => Carbon instance,
]
*/

// Get recent attempts
$attempts = $user->getRecentVerificationAttempts(limit: 10);

// Get successful/failed attempts
$successful = $user->getSuccessfulVerificationAttempts();
$failed = $user->getFailedVerificationAttempts();

// Clean up old attempts
$deleted = $user->clearFaceVerificationAttempts(daysToKeep: 7);
```

## Advanced Usage

### Update Reference Selfie

```php
// Requires verification against old selfie first
$user->updateReferenceSelfie(
    selfie: $request->file('new_selfie'),
    requireVerification: true,  // Verify it's the same person
    checkLiveness: true,
    metadata: [
        'reason' => 'User requested update',
        'updated_by' => auth()->id(),
    ]
);
```

### Without Liveness Check

```php
// Skip liveness check (not recommended for production)
$result = $user->verifyFace(
    selfie: $request->file('selfie'),
    checkLiveness: false,
    context: ['action' => 'testing']
);
```

### Without Storing Attempts

```php
// Don't save attempt in audit trail
$result = $user->verifyFace(
    selfie: $request->file('selfie'),
    storeAttempt: false,
    context: ['action' => 'dry_run']
);
```

### Custom Model (Contact, Employee, etc.)

```php
use LBHurtado\HyperVerge\Traits\HasFaceVerification;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contact extends Model implements HasMedia
{
    use InteractsWithMedia, HasFaceVerification;
    
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('face_reference_selfies')
            ->singleFile()
            ->useDisk('private'); // Private storage for contacts
            
        $this->addMediaCollection('face_verification_attempts')
            ->useDisk('private');
    }
}

// Usage
$contact = Contact::create(['name' => 'John Doe', 'mobile' => '+639171234567']);
$contact->enrollFace($selfieFile);

// Later
$result = $contact->verifyFace($newSelfieFile, context: ['action' => 'contact_verification']);
```

## Use Cases

### 1. Login by Face

```php
Route::post('/login/face', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'selfie' => 'required|file|image|max:5120',
    ]);

    $user = User::where('email', $request->email)->first();
    
    if (!$user || !$user->hasReferenceSelfie()) {
        return response()->json([
            'error' => 'Face verification not set up for this account'
        ], 400);
    }
    
    $result = $user->verifyFace(
        selfie: $request->file('selfie'),
        context: [
            'action' => 'login',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]
    );
    
    if ($result->verified) {
        Auth::login($user);
        
        return response()->json([
            'success' => true,
            'user' => $user->only(['id', 'name', 'email']),
            'confidence' => $result->matchConfidence,
        ]);
    }
    
    return response()->json([
        'error' => 'Face verification failed',
        'reason' => $result->failureReason,
    ], 401);
});
```

### 2. Payment Authorization

```php
Route::post('/payments/{id}/authorize', function (Request $request, $id) {
    $request->validate([
        'selfie' => 'required|file|image',
    ]);

    $payment = Payment::findOrFail($id);
    $user = $request->user();
    
    // Verify it's the payment owner
    if ($payment->user_id !== $user->id) {
        abort(403, 'Unauthorized');
    }
    
    $result = $user->verifyFace(
        selfie: $request->file('selfie'),
        context: [
            'action' => 'payment_authorization',
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]
    );
    
    if ($result->verified) {
        $payment->update([
            'status' => 'authorized',
            'authorized_at' => now(),
            'authorization_method' => 'face_verification',
            'authorization_confidence' => $result->matchConfidence,
        ]);
        
        return response()->json(['success' => true, 'payment' => $payment]);
    }
    
    return response()->json([
        'error' => 'Authorization failed',
        'reason' => $result->failureReason,
    ], 401);
});
```

### 3. Document Acknowledgment/Signing

```php
Route::post('/documents/{id}/acknowledge', function (Request $request, $id) {
    $document = Document::findOrFail($id);
    $user = $request->user();
    
    $result = $user->verifyFace(
        selfie: $request->file('selfie'),
        context: [
            'action' => 'document_acknowledgment',
            'document_id' => $document->id,
            'document_type' => $document->type,
        ]
    );
    
    if ($result->verified) {
        $acknowledgment = $document->acknowledgments()->create([
            'user_id' => $user->id,
            'acknowledged_at' => now(),
            'verification_method' => 'face_verification',
            'verification_confidence' => $result->matchConfidence,
            'ip_address' => $request->ip(),
        ]);
        
        return response()->json([
            'success' => true,
            'acknowledgment' => $acknowledgment,
        ]);
    }
    
    return response()->json([
        'error' => 'Acknowledgment failed',
        'reason' => $result->failureReason,
    ], 401);
});
```

### 4. Access Control

```php
class FaceVerificationMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!$request->hasFile('selfie')) {
            return response()->json(['error' => 'Selfie required'], 400);
        }
        
        $user = $request->user();
        
        if (!$user->hasReferenceSelfie()) {
            return response()->json(['error' => 'Face verification not enrolled'], 400);
        }
        
        $result = $user->verifyFace(
            selfie: $request->file('selfie'),
            context: [
                'action' => 'access_control',
                'route' => $request->path(),
            ]
        );
        
        if (!$result->verified) {
            return response()->json([
                'error' => 'Face verification failed',
                'reason' => $result->failureReason,
            ], 401);
        }
        
        // Attach result to request for controller access
        $request->attributes->add(['face_verification_result' => $result]);
        
        return $next($request);
    }
}

// Usage
Route::middleware(['auth', FaceVerificationMiddleware::class])->group(function () {
    Route::get('/sensitive-data', function (Request $request) {
        $result = $request->attributes->get('face_verification_result');
        
        return response()->json([
            'data' => 'sensitive information',
            'verified_with_confidence' => $result->matchConfidence,
        ]);
    });
});
```

## Configuration

All configuration is in `config/hyperverge.php`:

```php
'face_verification' => [
    // Enable/disable face verification
    'enabled' => env('HYPERVERGE_FACE_VERIFICATION_ENABLED', true),
    
    // Liveness check settings
    'require_liveness' => env('HYPERVERGE_FACE_LIVENESS_REQUIRED', true),
    'min_liveness_score' => env('HYPERVERGE_FACE_MIN_LIVENESS', 0.8), // 0.0-1.0
    
    // Face match settings
    'min_match_confidence' => env('HYPERVERGE_FACE_MIN_MATCH', 0.85), // 0.0-1.0
    
    // Storage settings
    'store_verification_attempts' => env('HYPERVERGE_STORE_FACE_ATTEMPTS', true),
    'attempts_retention_days' => env('HYPERVERGE_FACE_ATTEMPTS_RETENTION', 30),
    
    // Image validation
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'min_width' => 200,
    'min_height' => 200,
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/jpg'],
],
```

### Threshold Tuning

**Liveness Score** (`min_liveness_score`):
- `0.6-0.7`: Lenient - More false positives, fewer false negatives
- `0.8`: Balanced (default)
- `0.9-1.0`: Strict - Fewer false positives, more false negatives

**Match Confidence** (`min_match_confidence`):
- `0.7-0.8`: Lenient - Easier to match, higher risk
- `0.85`: Balanced (default)
- `0.9-0.95`: Strict - Harder to match, lower risk

**Recommendation**: Start with defaults, adjust based on your false positive/negative tolerance.

## Security Best Practices

### 1. Always Enable Liveness Check

```php
// ✅ Good
$user->enrollFace($selfie, checkLiveness: true);

// ❌ Bad (vulnerable to photo attacks)
$user->enrollFace($selfie, checkLiveness: false);
```

### 2. Use Private Storage

```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('face_reference_selfies')
        ->singleFile()
        ->useDisk('private'); // ✅ Not publicly accessible
}
```

### 3. Store Context with Attempts

```php
$result = $user->verifyFace(
    selfie: $selfie,
    context: [
        'action' => 'payment',
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'amount' => $payment->amount,
        'timestamp' => now(),
    ]
);
```

### 4. Rate Limiting

```php
Route::post('/login/face')
    ->middleware('throttle:5,1'); // 5 attempts per minute
```

### 5. GDPR Compliance

```php
// Allow users to delete their face data
Route::delete('/profile/face-verification', function (Request $request) {
    $user = $request->user();
    
    // Delete reference selfie
    $user->clearMediaCollection('face_reference_selfies');
    
    // Delete verification attempts
    $user->clearMediaCollection('face_verification_attempts');
    
    // Delete archives
    $user->clearMediaCollection('face_reference_selfies_archive');
    
    return response()->json(['success' => true]);
});
```

### 6. Audit Logging

```php
// Listen to events
Event::listen(FaceVerificationSucceeded::class, function ($event) {
    Log::info('Face verification succeeded', [
        'model_type' => get_class($event->model),
        'model_id' => $event->model->getKey(),
        'confidence' => $event->result->matchConfidence,
        'context' => $event->context,
    ]);
});

Event::listen(FaceVerificationFailed::class, function ($event) {
    Log::warning('Face verification failed', [
        'model_type' => get_class($event->model),
        'model_id' => $event->model->getKey(),
        'reason' => $event->result->failureReason,
        'context' => $event->context,
    ]);
});
```

## Troubleshooting

### Issue: "No reference selfie enrolled"

**Cause**: User hasn't enrolled a reference selfie yet.

**Solution**:
```php
if (!$user->hasReferenceSelfie()) {
    return redirect()->route('profile.enroll-face')
        ->with('message', 'Please enroll your face first');
}
```

### Issue: Liveness Check Always Fails

**Cause**: Poor lighting, image quality, or threshold too high.

**Solutions**:
1. Lower `min_liveness_score` in config (e.g., from 0.8 to 0.7)
2. Improve camera setup / lighting
3. Use higher resolution images

### Issue: Face Match Always Fails

**Cause**: Reference selfie vs verification selfie mismatch (lighting, angle, etc.)

**Solutions**:
1. Lower `min_match_confidence` (e.g., from 0.85 to 0.80)
2. Re-enroll with better quality reference selfie
3. Ensure consistent lighting/angle between enrollment and verification

### Issue: High False Positive Rate

**Cause**: Thresholds too low, allowing incorrect matches.

**Solutions**:
1. Increase `min_match_confidence` to 0.90 or higher
2. Increase `min_liveness_score` to 0.85 or higher
3. Always enable liveness checks

### Issue: Out of Memory When Processing

**Cause**: Image file too large.

**Solutions**:
1. Reduce `max_file_size` in config
2. Resize images on client side before upload
3. Compress images

### Issue: Cleanup Job Not Running

**Cause**: Scheduler not configured or queue not running.

**Solutions**:
1. Ensure cron is set up:
   ```bash
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```
2. Run queue worker:
   ```bash
   php artisan queue:work
   ```
3. Manually dispatch job:
   ```php
   CleanupFaceVerificationAttempts::dispatch();
   ```

### Debug Mode

Enable detailed logging:

```php
// In .env
LOG_LEVEL=debug

// View logs
tail -f storage/logs/laravel.log | grep '\[EnrollFace\]\|\[VerifyFace\]'
```

## Events

Listen to face verification events for custom logic:

```php
// In EventServiceProvider
use LBHurtado\HyperVerge\Events\FaceVerification\*;

protected $listen = [
    FaceEnrolled::class => [
        SendEnrollmentNotification::class,
    ],
    FaceVerificationSucceeded::class => [
        LogSuccessfulVerification::class,
    ],
    FaceVerificationFailed::class => [
        LogFailedAttempt::class,
        NotifySecurityTeam::class,
    ],
    ReferenceSelfieUpdated::class => [
        NotifyUserOfChange::class,
    ],
];
```

## Migration from KYC to Face Verification

If you already have KYC selfies and want to enable face verification:

```php
use LBHurtado\HyperVerge\Actions\FaceVerification\EnrollFace;

// One-time migration
User::whereNotNull('kyc_completed_at')
    ->whereHas('media', fn($q) => $q->where('collection_name', 'kyc_selfies'))
    ->chunk(100, function ($users) {
        foreach ($users as $user) {
            $kycSelfie = $user->getFirstMedia('kyc_selfies');
            
            // Copy to face reference collection
            $user->addMediaFromDisk($kycSelfie->getPath(), $kycSelfie->disk)
                ->withCustomProperties([
                    'migrated_from_kyc' => true,
                    'migrated_at' => now(),
                ])
                ->toMediaCollection('face_reference_selfies');
                
            Log::info("Migrated face verification for user {$user->id}");
        }
    });
```

## API Reference

See the main [README.md](../README.md) for full API documentation of all methods and actions.

---

**Need Help?**
- Check logs: `storage/logs/laravel.log`
- Review configuration: `config/hyperverge.php`
- Test with artisan commands: `php artisan hyperverge:verify-face --help`
