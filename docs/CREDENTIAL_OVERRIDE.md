# Multi-Level Credential Override Strategy

The HyperVerge package supports flexible credential management with a **precedence chain** that allows different API credentials at the campaign, user, and environment levels.

## Overview

**Precedence Order** (highest to lowest):
1. **Campaign-level credentials** - Stored in `Campaign.config['hyperverge']`
2. **User-level credentials** - Stored in `User.hyperverge_credentials` (encrypted)
3. **Environment credentials** - From `config/hyperverge.php` (default)

This enables:
- Multi-tenant scenarios (different campaigns using different HyperVerge accounts)
- User-specific credentials (for white-label solutions)
- Secure fallback to environment defaults

---

## Architecture

### Components

1. **HypervergeCredentials** (DTO)
   - Type-safe credential container
   - Factory methods for each source
   - Includes: `appId`, `appKey`, `workflowId`, `baseUrl`

2. **CredentialResolverInterface** (Contract)
   - Defines `resolve(?Model $context)` method
   - Implementations handle precedence logic

3. **HypervergeCredentialResolver** (Service)
   - Implements precedence chain
   - Extracts Campaign/User from context
   - Validates credential completeness

4. **HypervergeClientFactory** (Factory)
   - Creates `HypervergeClient` instances
   - Integrates with resolver
   - Maintains backward compatibility

---

## Configuration

### 1. Environment-Level (Default)

Set in `.env`:

```env
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=your_default_app_id
HYPERVERGE_APP_KEY=your_default_app_key
HYPERVERGE_URL_WORKFLOW=onboarding
```

Used when no campaign or user credentials are provided.

### 2. Campaign-Level

Store credentials in the `config` JSON field of the `campaigns` table:

```php
use App\Models\Campaign;

$campaign = Campaign::create([
    'name' => 'Partner XYZ Onboarding',
    'workflow_id' => 'partner_workflow',
    'config' => [
        'hyperverge' => [
            'app_id' => 'partner_app_id',
            'app_key' => 'partner_app_key',
            'workflow_id' => 'custom_workflow', // Optional override
            'base_url' => 'https://sgp.idv.hyperverge.co/v1', // Optional region
        ],
        'inputs' => [...],
        'theme' => [...],
    ],
]);
```

**Accessing credentials**:

```php
// Check if campaign has custom credentials
if ($campaign->hasHypervergeCredentials()) {
    $credentials = $campaign->getHypervergeCredentials();
}
```

**Use case**: Different marketing campaigns or partnerships using separate HyperVerge accounts.

### 3. User-Level

Store encrypted credentials in `users.hyperverge_credentials`:

```php
use App\Models\User;

$user = User::find(1);

// Set credentials (automatically encrypted)
$user->hyperverge_credentials = [
    'app_id' => 'user_specific_app_id',
    'app_key' => 'user_specific_app_key',
    'workflow_id' => 'user_workflow', // Optional
];
$user->save();

// Access credentials (automatically decrypted)
$credentials = $user->hyperverge_credentials;
```

**Use case**: White-label solutions where each organization/user has their own HyperVerge account.

---

## Usage in Code

### Automatic Resolution (Recommended)

Actions automatically resolve credentials when you pass a context model:

```php
use LBHurtado\HyperVerge\Actions\LinkKYC\GenerateOnboardingLink;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;

// Campaign context
$campaign = Campaign::find($uuid);
$url = GenerateOnboardingLink::get(
    transactionId: 'txn_123',
    context: $campaign  // Uses campaign credentials if available
);

// CampaignSubmission context (extracts campaign)
$submission = CampaignSubmission::find($id);
$result = FetchKYCResult::run(
    transactionId: $submission->transaction_id,
    context: $submission  // Uses submission's campaign credentials
);

// User context
$user = User::find($userId);
$url = GenerateOnboardingLink::get(
    transactionId: 'user_' . $user->id,
    context: $user  // Uses user credentials if available
);

// No context - uses environment credentials
$url = GenerateOnboardingLink::get(
    transactionId: 'default_txn'
    // Defaults to config/hyperverge.php
);
```

### Manual Resolution

For advanced scenarios:

```php
use LBHurtado\HyperVerge\Contracts\CredentialResolverInterface;
use LBHurtado\HyperVerge\Factories\HypervergeClientFactory;

$resolver = app(CredentialResolverInterface::class);

// Resolve credentials
$credentials = $resolver->resolve($campaign);

echo $credentials->source;  // "campaign:uuid-123"
echo $credentials->appId;   // "partner_app_id"

// Create client with resolved credentials
$factory = app(HypervergeClientFactory::class);
$client = $factory->make($campaign);

// Make API call
$response = $client->post('/link-kyc/start', [...]);
```

---

## Precedence Examples

### Example 1: Campaign Overrides User

```php
$user = User::find(1);
$user->hyperverge_credentials = [
    'app_id' => 'user_app',
    'app_key' => 'user_key',
];
$user->save();

$campaign = Campaign::create([
    'name' => 'Special Campaign',
    'config' => [
        'hyperverge' => [
            'app_id' => 'campaign_app',
            'app_key' => 'campaign_key',
        ],
    ],
]);

$submission = CampaignSubmission::create([
    'campaign_id' => $campaign->id,
    'transaction_id' => 'txn_123',
]);

// Resolve from submission (has both campaign and potential user)
$credentials = app(CredentialResolverInterface::class)->resolve($submission);

// Result: Uses CAMPAIGN credentials
echo $credentials->appId;  // "campaign_app" (not "user_app")
echo $credentials->source;  // "campaign:uuid-xxx"
```

### Example 2: User Overrides Environment

```php
$user = User::find(1);
$user->hyperverge_credentials = [
    'app_id' => 'user_specific_app',
    'app_key' => 'user_specific_key',
];
$user->save();

$credentials = app(CredentialResolverInterface::class)->resolve($user);

// Result: Uses USER credentials
echo $credentials->appId;  // "user_specific_app" (not from .env)
echo $credentials->source;  // "user:1"
```

### Example 3: Fallback to Environment

```php
$campaign = Campaign::create([
    'name' => 'Basic Campaign',
    // No hyperverge credentials in config
]);

$credentials = app(CredentialResolverInterface::class)->resolve($campaign);

// Result: Uses ENVIRONMENT credentials
echo $credentials->appId;  // From HYPERVERGE_APP_ID in .env
echo $credentials->source;  // "config"
```

---

## Security Considerations

### Campaign Credentials

- **Storage**: Plain JSON in `campaigns.config['hyperverge']`
- **Visibility**: Visible to database admins and anyone with DB access
- **Recommendation**: Use for less sensitive scenarios or implement additional database-level encryption

### User Credentials

- **Storage**: Encrypted using Laravel's `encrypted:array` cast
- **Encryption**: Uses `APP_KEY` from `.env`
- **Visibility**: Automatically encrypted/decrypted by Laravel
- **Recommendation**: Ideal for sensitive user-specific credentials

### Best Practices

1. **Rotate Keys Regularly**: Update credentials periodically
2. **Restrict Access**: Limit who can edit campaign configs or user credentials
3. **Audit Logs**: Log credential resolution for security monitoring
4. **Secure APP_KEY**: Protect your Laravel `APP_KEY` as it encrypts user credentials

---

## Debugging

### Enable Debug Logging

The credential resolver logs its decisions:

```bash
tail -f storage/logs/laravel.log | grep CredentialResolver
```

Example logs:

```
[CredentialResolver] Using campaign-level credentials (campaign_id: uuid-123, name: Partner XYZ)
[CredentialResolver] Using user-level credentials (user_id: 1, email: user@example.com)
[CredentialResolver] Using environment credentials
```

### Inspect Resolved Credentials

```php
$credentials = app(CredentialResolverInterface::class)->resolve($context);

// Safe debug output (masks sensitive data)
dd($credentials->toDebugArray());

// Output:
// [
//     'app_id' => 'part****_app',
//     'app_key' => 'part****_key',
//     'workflow_id' => 'onboarding',
//     'base_url' => 'https://ind.idv.hyperverge.co/v1',
//     'source' => 'campaign:uuid-123',
// ]
```

### Validate Credentials

```php
if (!$credentials->isValid()) {
    throw new \RuntimeException('Incomplete credentials: ' . json_encode($credentials->toDebugArray()));
}
```

---

## Testing

### Mock Credentials

```php
use LBHurtado\HyperVerge\Data\HypervergeCredentials;

// Create test credentials
$credentials = new HypervergeCredentials(
    appId: 'test_app_id',
    appKey: 'test_app_key',
    workflowId: 'test_workflow',
    baseUrl: 'https://test.hyperverge.co',
    source: 'test'
);

// Mock resolver
$this->mock(CredentialResolverInterface::class)
    ->shouldReceive('resolve')
    ->andReturn($credentials);
```

### Test Precedence

```php
public function test_campaign_credentials_override_user_credentials()
{
    $user = User::factory()->create([
        'hyperverge_credentials' => [
            'app_id' => 'user_app',
            'app_key' => 'user_key',
        ],
    ]);
    
    $campaign = Campaign::factory()->create([
        'config' => [
            'hyperverge' => [
                'app_id' => 'campaign_app',
                'app_key' => 'campaign_key',
            ],
        ],
    ]);
    
    $submission = CampaignSubmission::factory()->create([
        'campaign_id' => $campaign->id,
    ]);
    
    $resolver = app(CredentialResolverInterface::class);
    $credentials = $resolver->resolve($submission);
    
    $this->assertEquals('campaign_app', $credentials->appId);
    $this->assertStringContains('campaign:', $credentials->source);
}
```

---

## Migration Guide

If you have existing code using hardcoded credentials:

### Before (Old Way)

```php
$response = Http::withHeaders([
    'appId' => config('hyperverge.app_id'),
    'appKey' => config('hyperverge.app_key'),
])->post(...);
```

### After (New Way)

```php
$factory = app(HypervergeClientFactory::class);
$client = $factory->make($campaign);  // Automatic credential resolution
$response = $client->post(...);
```

**Backward compatibility**: Existing code without context still works (uses environment credentials).

---

## Extending

### Add New Credential Sources

Want to add Team or Organization-level credentials? Extend the resolver:

```php
namespace App\Services;

use LBHurtado\HyperVerge\Services\HypervergeCredentialResolver as BaseResolver;

class ExtendedCredentialResolver extends BaseResolver
{
    protected function resolve(?Model $context = null): HypervergeCredentials
    {
        // Add team-level precedence
        if ($team = $this->extractTeam($context)) {
            if ($this->teamHasCredentials($team)) {
                return HypervergeCredentials::fromTeam($team);
            }
        }
        
        // Fall back to parent logic
        return parent::resolve($context);
    }
}
```

Register in `AppServiceProvider`:

```php
$this->app->bind(
    CredentialResolverInterface::class,
    ExtendedCredentialResolver::class
);
```

---

## Summary

✅ **Campaign-level**: `campaigns.config['hyperverge']` (plain JSON)  
✅ **User-level**: `users.hyperverge_credentials` (encrypted)  
✅ **Environment-level**: `config/hyperverge.php` (default)  
✅ **Automatic resolution**: Pass context model to actions  
✅ **Secure**: User credentials are encrypted  
✅ **Flexible**: Easy to extend with custom sources  
✅ **Backward compatible**: Existing code still works

For questions or issues, check logs or debug using `credentials->toDebugArray()`.
