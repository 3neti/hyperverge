<?php

namespace LBHurtado\HyperVerge\Webhooks;

use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Actions\Results\ProcessKYCData;
use LBHurtado\HyperVerge\Actions\Results\StoreKYCImages;
use LBHurtado\HyperVerge\Actions\Results\ValidateKYCResult;
use LBHurtado\HyperVerge\Events\KYCApproved;
use LBHurtado\HyperVerge\Events\KYCRejected;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

/**
 * Process HyperVerge webhook.
 * 
 * This job is dispatched when a webhook is received from HyperVerge.
 * It orchestrates the complete KYC verification flow:
 * 
 * 1. Fetch KYC result from HyperVerge
 * 2. Validate result against business rules
 * 3. If valid: Store images and process data
 * 4. Dispatch appropriate event (KYCApproved or KYCRejected)
 * 
 * Usage:
 * 
 * // Configure in config/webhook-client.php
 * 'configs' => [
 *     [
 *         'name' => 'hyperverge',
 *         'signing_secret' => env('HYPERVERGE_WEBHOOK_SECRET'),
 *         'signature_header_name' => 'X-HyperVerge-Signature',
 *         'signature_validator' => \LBHurtado\HyperVerge\Webhooks\HypervergeSignatureValidator::class,
 *         'webhook_profile' => \LBHurtado\HyperVerge\Webhooks\HypervergeWebhookProfile::class,
 *         'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
 *         'process_webhook_job' => \LBHurtado\HyperVerge\Webhooks\ProcessHypervergeWebhookJob::class,
 *     ],
 * ],
 * 
 * // Define routes
 * Route::post('/webhooks/hyperverge', function (Request $request) {
 *     $webhookConfig = new WebhookConfig([
 *         'name' => 'hyperverge',
 *         'signing_secret' => config('hyperverge.webhook.secret'),
 *         'signature_header_name' => 'X-HyperVerge-Signature',
 *         'signature_validator' => HypervergeSignatureValidator::class,
 *         'webhook_profile' => HypervergeWebhookProfile::class,
 *         'webhook_model' => WebhookCall::class,
 *         'process_webhook_job' => ProcessHypervergeWebhookJob::class,
 *     ]);
 * 
 *     (new WebhookController)->__invoke($request, $webhookConfig);
 * });
 */
class ProcessHypervergeWebhookJob extends ProcessWebhookJob
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        
        // Extract transaction ID
        $transactionId = $payload['transactionId'] 
            ?? $payload['metadata']['transactionId'] 
            ?? null;

        if (!$transactionId) {
            Log::error('[HyperVerge Webhook] No transaction ID found', [
                'webhook_id' => $this->webhookCall->id,
                'payload' => $payload,
            ]);
            return;
        }

        Log::info('[HyperVerge Webhook] Processing webhook', [
            'webhook_id' => $this->webhookCall->id,
            'transaction_id' => $transactionId,
        ]);

        try {
            // Find model first (for credential resolution)
            $model = $this->findModelForTransaction($transactionId);
            
            // Step 1: Fetch full KYC result (with context for credential resolution)
            $result = FetchKYCResult::run($transactionId, $model);

            // Step 2: Validate result
            $validation = ValidateKYCResult::run($transactionId);

            // Step 3: Process based on validation
            if ($validation->valid) {
                Log::info('[HyperVerge Webhook] KYC approved', [
                    'transaction_id' => $transactionId,
                    'status' => $validation->status,
                ]);

                if ($model) {
                    // Store images if model implements HasMedia
                    if ($model instanceof \Spatie\MediaLibrary\HasMedia) {
                        $imageUrls = \LBHurtado\HyperVerge\Actions\Results\ExtractKYCImages::run($transactionId, $model);
                        if (!empty($imageUrls)) {
                            StoreKYCImages::run($model, $imageUrls, $transactionId);
                        }
                    }

                    // Process KYC data if model uses HasInputs
                    if (in_array(\LBHurtado\ModelInput\Traits\HasInputs::class, class_uses_recursive($model))) {
                        ProcessKYCData::run($model, $transactionId, includeAddress: true);
                    }
                }

                // Dispatch approved event
                KYCApproved::dispatch($result, $validation, $transactionId);

            } else {
                Log::warning('[HyperVerge Webhook] KYC rejected', [
                    'transaction_id' => $transactionId,
                    'reasons' => $validation->reasons,
                ]);

                // Dispatch rejected event
                KYCRejected::dispatch($result, $validation, $transactionId);
            }

        } catch (\Exception $e) {
            Log::error('[HyperVerge Webhook] Failed to process webhook', [
                'webhook_id' => $this->webhookCall->id,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger job retry
        }
    }

    /**
     * Find the model associated with this transaction.
     * 
     * Override this method in your application to implement your own logic
     * for finding the model (e.g. User) associated with a KYC transaction.
     *
     * @param string $transactionId
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function findModelForTransaction(string $transactionId): ?\Illuminate\Database\Eloquent\Model
    {
        // Default implementation: try to find by transaction ID field
        // You should override this in your app's service provider or create a custom job
        
        $modelClass = config('hyperverge.webhook.model_class', \App\Models\User::class);
        $transactionIdField = config('hyperverge.webhook.transaction_id_field', 'kyc_transaction_id');

        if (!class_exists($modelClass)) {
            Log::warning('[HyperVerge Webhook] Model class not found', [
                'model_class' => $modelClass,
            ]);
            return null;
        }

        try {
            return $modelClass::where($transactionIdField, $transactionId)->first();
        } catch (\Exception $e) {
            Log::warning('[HyperVerge Webhook] Failed to find model', [
                'model_class' => $modelClass,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
