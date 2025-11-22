# HyperVerge Laravel Package - Quick Start

## Installation

The package is already installed in this host app via local path repository.

```bash
# Already done in this project:
composer require lbhurtado/hyperverge:@dev
php artisan vendor:publish --tag=hyperverge-config
```

## Configuration

Add your HyperVerge credentials to `.env`:

```env
HYPERVERGE_BASE_URL=https://api.hyperverge.co
HYPERVERGE_APP_ID=your-app-id-here
HYPERVERGE_APP_KEY=your-app-key-here
HYPERVERGE_TIMEOUT=30
```

## Basic Usage

### 1. Selfie Liveness Check

```php
use LBHurtado\HyperVerge\Services\SelfieLivenessService;

Route::post('/verify-selfie', function (Request $request) {
    $service = app(SelfieLivenessService::class);
    
    try {
        $result = $service->verify($request->input('selfie_base64'));
        
        return response()->json([
            'success' => true,
            'is_live' => $result->isLive,
            'quality' => $result->quality,
        ]);
    } catch (\Hyperverge\Exceptions\LivelinessFailedException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Liveness check failed',
            'details' => $e->getResponse(),
        ], 422);
    }
});
```

### 2. Face Matching

```php
use LBHurtado\HyperVerge\Services\FaceMatchService;

Route::post('/match-faces', function (Request $request) {
    $service = app(FaceMatchService::class);
    
    try {
        $result = $service->match(
            $request->input('reference_image'),
            $request->input('selfie_image')
        );
        
        return response()->json([
            'success' => true,
            'is_match' => $result->isMatch,
            'confidence' => $result->confidence,
            'quality' => $result->quality,
        ]);
    } catch (\Hyperverge\Exceptions\FaceMatchFailedException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Face match failed',
        ], 422);
    }
});
```

### 3. Combined Pipeline (Liveness + Face Match)

```php
use LBHurtado\HyperVerge\Actions\VerifySelfieAndMatch;

Route::post('/verify-and-match', function (Request $request) {
    $action = app(VerifySelfieAndMatch::class);
    
    $result = $action->executeSafe(
        $request->input('reference_image'),
        $request->input('selfie_image')
    );
    
    if ($result['success']) {
        return response()->json([
            'success' => true,
            'liveness' => [
                'is_live' => $result['liveness']->isLive,
                'quality' => $result['liveness']->quality,
            ],
            'match' => [
                'is_match' => $result['match']->isMatch,
                'confidence' => $result['match']->confidence,
            ],
        ]);
    }
    
    return response()->json([
        'success' => false,
        'error' => $result['error'] ?? 'Verification failed',
    ], 422);
});
```

### 4. Link-Based KYC Session

```php
use LBHurtado\HyperVerge\Services\LinkKYCService;

Route::post('/create-kyc-session', function (Request $request) {
    $service = app(LinkKYCService::class);
    
    $session = $service->createSession(
        callbackUrl: route('kyc.callback'),
        metadata: [
            'user_id' => auth()->id(),
            'email' => auth()->user()->email,
        ],
        config: [
            'modules' => ['idCard', 'selfie', 'faceMatch'],
        ]
    );
    
    return response()->json([
        'success' => true,
        'session_id' => $session['sessionId'] ?? null,
        'link' => $session['link'] ?? null,
    ]);
});
```

### 5. Fetch KYC Results

```php
use LBHurtado\HyperVerge\Services\ResultsService;

Route::get('/kyc-result/{sessionId}', function (string $sessionId) {
    $service = app(ResultsService::class);
    $result = $service->fetch($sessionId);
    
    return response()->json([
        'status' => $result->applicationStatus,
        'modules' => collect($result->modules)->map(fn($module) => [
            'name' => $module->name,
            'status' => $module->status,
            'details' => $module->details,
        ]),
    ]);
});
```

### 6. Webhook Handler

The webhook route is automatically registered:

```
POST /hyperverge/webhook
```

Listen to webhook events in your application:

```php
// In EventServiceProvider or listener
Event::listen('hyperverge.webhook.received', function ($webhookData) {
    Log::info('HyperVerge Webhook', [
        'session_id' => $webhookData->sessionId,
        'status' => $webhookData->status,
    ]);
    
    // Process webhook, update database, notify user, etc.
});
```

## Using DTOs Directly

For more control, use DTOs directly:

```php
use LBHurtado\HyperVerge\Data\Requests\SelfieLivenessRequestData;
use LBHurtado\HyperVerge\Services\SelfieLivenessService;

$request = SelfieLivenessRequestData::fromBase64($base64Image);
$service = app(SelfieLivenessService::class);
$result = $service->verifyFromRequest($request);
```

## Working with KYC Modules

```php
use LBHurtado\HyperVerge\Services\ResultsService;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;

$service = app(ResultsService::class);
$result = $service->fetch($sessionId);

// Get specific module
$idCard = $result->getModuleByName('ID Card Validation');

if ($idCard instanceof IdCardModuleData) {
    echo $idCard->idNumber();
    echo $idCard->fullName();
    echo $idCard->country();
    echo $idCard->idType();
}

// Or iterate all modules
foreach ($result->modules as $module) {
    echo $module->name . ': ' . $module->status;
}
```

## Error Handling

```php
use LBHurtado\HyperVerge\Exceptions\HypervergeException;
use LBHurtado\HyperVerge\Exceptions\LivelinessFailedException;
use LBHurtado\HyperVerge\Exceptions\FaceMatchFailedException;

try {
    $service = app(SelfieLivenessService::class);
    $result = $service->verify($image);
} catch (LivelinessFailedException $e) {
    // Liveness check failed
    $response = $e->getResponse();
    $quality = $response['result']['quality'] ?? [];
} catch (HypervergeException $e) {
    // Generic HyperVerge error
    Log::error('HyperVerge Error', [
        'message' => $e->getMessage(),
        'response' => $e->getResponse(),
    ]);
}
```

## Testing

Mock HTTP responses in tests:

```php
use Illuminate\Support\Facades\Http;
use LBHurtado\HyperVerge\Services\SelfieLivenessService;

it('can verify liveness', function () {
    Http::fake([
        '*/v1/selfie/liveness' => Http::response([
            'result' => [
                'isLive' => true,
                'quality' => ['blur' => false],
            ],
        ]),
    ]);
    
    $service = app(SelfieLivenessService::class);
    $result = $service->verify('fake_image');
    
    expect($result->isLive)->toBeTrue();
});
```

## Available Services

All services are bound in the container and can be injected:

```php
use LBHurtado\HyperVerge\Services\SelfieLivenessService;
use LBHurtado\HyperVerge\Services\FaceMatchService;
use LBHurtado\HyperVerge\Services\LinkKYCService;
use LBHurtado\HyperVerge\Services\ResultsService;
use LBHurtado\HyperVerge\Actions\VerifySelfieAndMatch;

class MyController extends Controller
{
    public function __construct(
        private SelfieLivenessService $liveness,
        private FaceMatchService $faceMatch,
        private LinkKYCService $kyc,
        private ResultsService $results,
    ) {}
}
```

## Next Steps

1. Add your HyperVerge credentials to `.env`
2. Create routes using the examples above
3. Test with actual API calls
4. Handle webhooks for async results
5. Add frontend components for file upload

## Documentation

- Full implementation details: See `IMPLEMENTATION.md`
- Package README: See `packages/hyperverge-php/README.md`
- Test examples: See `packages/hyperverge-php/tests/`

## Support

For issues with the package implementation, check:
- Configuration: `config/hyperverge.php`
- Environment variables in `.env`
- Laravel logs: `storage/logs/laravel.log`
