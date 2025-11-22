<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Events\KYCDataSaved;
use Spatie\MediaLibrary\HasMedia;

/**
 * Atomic transaction manager for KYC data and image storage.
 * 
 * This action combines image downloading/validation and data extraction/validation
 * into a single atomic transaction. If any step fails, everything rolls back.
 * 
 * Benefits:
 * - Single atomic operation (all-or-nothing)
 * - Comprehensive error reporting
 * - Event dispatch on success
 * - Detailed logging
 * 
 * @example
 * $result = SaveKYCDataWithTransaction::run(
 *     $submission, 
 *     $transactionId,
 *     includeAddress: true,
 *     skipDuplicateImages: true,
 *     overwriteExistingData: true,
 *     strictValidation: false
 * );
 * 
 * if ($result['success']) {
 *     echo "✅ Saved {$result['images']['stored_count']} images and {$result['data']['stored_count']} fields";
 * } else {
 *     echo "❌ Failed: {$result['error']}";
 *     // Check detailed errors
 *     dd($result['images']['errors'], $result['data']['validation_errors']);
 * }
 */
class SaveKYCDataWithTransaction
{
    use AsAction;

    /**
     * Save KYC data and images in a single transaction.
     *
     * @param Model&HasMedia $model The model to store data and images on
     * @param string $transactionId The HyperVerge transaction ID
     * @param bool $includeAddress Whether to include address field
     * @param bool $skipDuplicateImages Whether to skip already downloaded images
     * @param bool $overwriteExistingData Whether to overwrite existing data fields
     * @param bool $strictValidation If true, fail on any validation error
     * @param Model|null $context Campaign, CampaignSubmission, User for credential resolution
     * @return array Comprehensive result with success status and details
     */
    public function handle(
        Model $model,
        string $transactionId,
        bool $includeAddress = false,
        bool $skipDuplicateImages = true,
        bool $overwriteExistingData = true,
        bool $strictValidation = false,
        ?Model $context = null
    ): array {
        $result = [
            'success' => false,
            'transaction_id' => $transactionId,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'images' => [],
            'data' => [],
            'error' => null,
            'started_at' => now()->toIso8601String(),
            'completed_at' => null,
        ];

        Log::info('[SaveKYCDataWithTransaction] Starting atomic KYC data save', [
            'transaction_id' => $transactionId,
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'options' => [
                'include_address' => $includeAddress,
                'skip_duplicate_images' => $skipDuplicateImages,
                'overwrite_existing_data' => $overwriteExistingData,
                'strict_validation' => $strictValidation,
            ],
        ]);

        try {
            // Start outer transaction
            DB::beginTransaction();

            // Step 1: Extract image URLs
            Log::info('[SaveKYCDataWithTransaction] Extracting image URLs...');
            
            $imageUrls = ExtractKYCImages::run($transactionId, $context);
            
            if (empty($imageUrls)) {
                Log::warning('[SaveKYCDataWithTransaction] No images found', [
                    'transaction_id' => $transactionId,
                ]);
            }

            // Step 2: Store images with validation
            Log::info('[SaveKYCDataWithTransaction] Storing images with validation...');
            
            $imageResult = StoreKYCImagesWithValidation::run(
                $model,
                $imageUrls,
                $transactionId,
                $skipDuplicateImages
            );

            $result['images'] = $imageResult;

            // Check image storage success (unless no images expected)
            if (!$imageResult['success'] && !empty($imageUrls)) {
                throw new \RuntimeException(
                    'Image storage failed: ' . ($imageResult['errors']['transaction'] ?? 'Unknown error')
                );
            }

            Log::info('[SaveKYCDataWithTransaction] Images stored', [
                'stored' => $imageResult['stored_count'],
                'skipped' => count($imageResult['skipped']),
                'errors' => count($imageResult['errors']),
            ]);

            // Step 3: Process data with validation
            Log::info('[SaveKYCDataWithTransaction] Processing KYC data with validation...');
            
            $dataResult = ProcessKYCDataWithValidation::run(
                $model,
                $transactionId,
                $includeAddress,
                $strictValidation,
                $overwriteExistingData,
                $context
            );

            $result['data'] = $dataResult;

            // Check data processing success
            if (!$dataResult['success']) {
                throw new \RuntimeException(
                    'Data processing failed: ' . ($dataResult['validation_errors']['transaction'] ?? 'Unknown error')
                );
            }

            Log::info('[SaveKYCDataWithTransaction] Data processed', [
                'stored' => count($dataResult['stored_inputs']),
                'skipped' => count($dataResult['skipped_inputs']),
                'errors' => count($dataResult['validation_errors']),
            ]);

            // Commit outer transaction
            DB::commit();
            
            $result['success'] = true;
            $result['completed_at'] = now()->toIso8601String();

            // Dispatch success event
            KYCDataSaved::dispatch($model, $transactionId, $result);

            Log::info('[SaveKYCDataWithTransaction] Transaction completed successfully', [
                'transaction_id' => $transactionId,
                'images_stored' => $imageResult['stored_count'],
                'data_fields_stored' => count($dataResult['stored_inputs']),
                'duration_ms' => now()->diffInMilliseconds($result['started_at']),
            ]);

        } catch (\Exception $e) {
            // Rollback everything on any failure
            DB::rollBack();
            
            $result['success'] = false;
            $result['error'] = $e->getMessage();
            $result['completed_at'] = now()->toIso8601String();

            Log::error('[SaveKYCDataWithTransaction] Transaction failed - rolled back', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'images_attempted' => $result['images']['stored_count'] ?? 0,
                'data_fields_attempted' => count($result['data']['stored_inputs'] ?? []),
            ]);
        }

        return $result;
    }

    /**
     * Get summary message from result.
     */
    public static function getSummary(array $result): string
    {
        if ($result['success']) {
            $imagesCount = $result['images']['stored_count'] ?? 0;
            $dataCount = count($result['data']['stored_inputs'] ?? []);
            
            return "✅ Successfully saved {$imagesCount} images and {$dataCount} data fields for transaction {$result['transaction_id']}";
        } else {
            return "❌ Failed to save KYC data: {$result['error']}";
        }
    }

    /**
     * Check if result has warnings (partial success).
     */
    public static function hasWarnings(array $result): bool
    {
        if (!$result['success']) {
            return false;
        }

        $imageWarnings = !empty($result['images']['skipped']) || !empty($result['images']['errors']);
        $dataWarnings = !empty($result['data']['skipped_inputs']) || !empty($result['data']['validation_errors']);

        return $imageWarnings || $dataWarnings;
    }

    /**
     * Get all warnings from result.
     */
    public static function getWarnings(array $result): array
    {
        $warnings = [];

        // Image warnings
        if (!empty($result['images']['skipped'])) {
            foreach ($result['images']['skipped'] as $key => $reason) {
                $warnings[] = "Image '{$key}' skipped: {$reason}";
            }
        }
        if (!empty($result['images']['errors'])) {
            foreach ($result['images']['errors'] as $key => $error) {
                $warnings[] = "Image '{$key}' error: {$error}";
            }
        }

        // Data warnings
        if (!empty($result['data']['skipped_inputs'])) {
            foreach ($result['data']['skipped_inputs'] as $field) {
                $warnings[] = "Data field '{$field}' skipped (already exists)";
            }
        }
        if (!empty($result['data']['validation_errors'])) {
            foreach ($result['data']['validation_errors'] as $field => $error) {
                $warnings[] = "Data field '{$field}' validation error: " . 
                    (is_array($error) ? implode(', ', $error) : $error);
            }
        }

        return $warnings;
    }
}
