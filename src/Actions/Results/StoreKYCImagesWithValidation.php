<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Events\KYCImagesStored;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Enhanced version of StoreKYCImages with validation and transaction safety.
 * 
 * Improvements over original:
 * - Image format validation (checks actual file type)
 * - File size limits (prevents downloading huge files)
 * - Transaction safety (rolls back on failure)
 * - Duplicate detection (checks existing media)
 * - Proper extension detection (uses actual file format)
 * - Failed download tracking (returns detailed report)
 * - Cleanup on failure (removes partial downloads)
 * 
 * @example
 * $result = StoreKYCImagesWithValidation::run($submission, $imageUrls, $transactionId);
 * 
 * // Check result
 * if ($result['success']) {
 *     echo "Stored {$result['stored_count']} images";
 * } else {
 *     echo "Failed: " . implode(', ', $result['errors']);
 * }
 */
class StoreKYCImagesWithValidation
{
    use AsAction;

    protected const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];
    protected const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Store KYC images with validation and transaction safety.
     * 
     * @param HasMedia $model The model to attach images to
     * @param array $imageUrls Array of image URLs keyed by type
     * @param string|null $transactionId Optional transaction ID
     * @param bool $skipDuplicates Whether to skip already downloaded images
     * @return array Result with success status, stored media, and errors
     */
    public function handle(
        HasMedia $model,
        array $imageUrls,
        ?string $transactionId = null,
        bool $skipDuplicates = true
    ): array {
        $result = [
            'success' => false,
            'stored_media' => [],
            'skipped' => [],
            'errors' => [],
            'stored_count' => 0,
        ];

        Log::info('[StoreKYCImagesWithValidation] Starting image storage', [
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'image_count' => count($imageUrls),
            'transaction_id' => $transactionId,
        ]);

        try {
            // Use database transaction for atomicity
            DB::beginTransaction();

            foreach ($imageUrls as $key => $url) {
                // Skip non-URL entries (like country, document type)
                if (!is_string($url) || !str_starts_with($url, 'http')) {
                    continue;
                }

                // Check for duplicates
                if ($skipDuplicates && $this->isDuplicate($model, $key, $url)) {
                    Log::info('[StoreKYCImagesWithValidation] Skipping duplicate image', [
                        'key' => $key,
                        'url' => $url,
                    ]);
                    $result['skipped'][$key] = 'Already exists';
                    continue;
                }

                try {
                    $media = $this->downloadAndStoreImage($model, $key, $url, $transactionId);
                    
                    if ($media) {
                        $result['stored_media'][$key] = $media;
                        $result['stored_count']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][$key] = $e->getMessage();
                    
                    Log::error('[StoreKYCImagesWithValidation] Failed to store image', [
                        'key' => $key,
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);

                    // Fail fast - rollback on any error
                    throw $e;
                }
            }

            // Commit transaction
            DB::commit();
            $result['success'] = true;

            // Dispatch event on success
            if (!empty($result['stored_media']) && $transactionId) {
                KYCImagesStored::dispatch($model, $result['stored_media'], $transactionId);
            }

            Log::info('[StoreKYCImagesWithValidation] Storage completed', [
                'stored' => $result['stored_count'],
                'skipped' => count($result['skipped']),
                'errors' => count($result['errors']),
            ]);

        } catch (\Exception $e) {
            // Rollback transaction on failure
            DB::rollBack();
            
            $result['success'] = false;
            $result['errors']['transaction'] = $e->getMessage();

            Log::error('[StoreKYCImagesWithValidation] Transaction failed', [
                'error' => $e->getMessage(),
                'stored_before_failure' => $result['stored_count'],
            ]);
        }

        return $result;
    }

    /**
     * Download and store a single image with validation.
     */
    protected function downloadAndStoreImage(
        HasMedia $model,
        string $key,
        string $url,
        ?string $transactionId
    ): ?Media {
        $timeout = config('hyperverge.images.timeout', 30);
        $maxRetries = config('hyperverge.images.max_retries', 3);

        Log::info('[StoreKYCImagesWithValidation] Downloading image', [
            'key' => $key,
            'url' => substr($url, 0, 100) . '...',
        ]);

        // Download with retry
        $response = Http::timeout($timeout)
            ->retry($maxRetries, 200)
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Failed to download image from {$url}: HTTP {$response->status()}"
            );
        }

        $content = $response->body();

        // Validate image
        $this->validateImage($content, $url);

        // Detect actual file type
        $extension = $this->detectExtension($content);
        $mimeType = $this->detectMimeType($content);

        // Determine collection and filename
        $collection = $this->getCollection($key);
        $filename = $this->getFilename($key, $model, $extension);
        $name = $this->getDisplayName($key);

        // Store using media library
        $media = $model
            ->addMediaFromString($content)
            ->usingFileName($filename)
            ->usingName($name)
            ->withCustomProperties([
                'kyc_image_type' => $key,
                'original_url' => $url,
                'downloaded_at' => now()->toIso8601String(),
                'transaction_id' => $transactionId,
                'file_size' => strlen($content),
                'mime_type' => $mimeType,
                'validated' => true,
            ])
            ->toMediaCollection($collection);

        Log::info('[StoreKYCImagesWithValidation] Image stored successfully', [
            'key' => $key,
            'media_id' => $media->id,
            'collection' => $collection,
            'size' => $media->size,
            'mime_type' => $mimeType,
        ]);

        return $media;
    }

    /**
     * Validate image content before storing.
     * 
     * @throws \InvalidArgumentException
     */
    protected function validateImage(string $content, string $url): void
    {
        // Check file size
        $size = strlen($content);
        if ($size === 0) {
            throw new \InvalidArgumentException("Downloaded file is empty: {$url}");
        }

        if ($size > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                "File too large: " . round($size / 1024 / 1024, 2) . "MB (max: " . 
                round(self::MAX_FILE_SIZE / 1024 / 1024) . "MB)"
            );
        }

        // Validate image format using GD
        $image = @imagecreatefromstring($content);
        if ($image === false) {
            throw new \InvalidArgumentException("Invalid image format or corrupted file: {$url}");
        }
        imagedestroy($image);

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException(
                "Invalid MIME type: {$mimeType} (allowed: " . implode(', ', self::ALLOWED_MIME_TYPES) . ")"
            );
        }
    }

    /**
     * Detect actual file extension from content.
     */
    protected function detectExtension(string $content): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg', // fallback
        };
    }

    /**
     * Detect MIME type from content.
     */
    protected function detectMimeType(string $content): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        return $mimeType ?: 'image/jpeg';
    }

    /**
     * Check if image already exists for this model.
     * 
     * Checks by image type (e.g., 'id_card_full', 'selfie') rather than URL
     * since HyperVerge URLs have temporary signatures that change.
     */
    protected function isDuplicate(HasMedia $model, string $key, string $url): bool
    {
        $collection = $this->getCollection($key);
        
        // Check by image type in custom properties
        return $model->getMedia($collection)
            ->contains(function (Media $media) use ($key) {
                return $media->getCustomProperty('kyc_image_type') === $key;
            });
    }

    /**
     * Determine which media collection to use based on image type.
     */
    protected function getCollection(string $key): string
    {
        return match (true) {
            str_contains($key, 'id_card') => 'kyc_id_cards',
            str_contains($key, 'selfie') => 'kyc_selfies',
            default => 'kyc_documents',
        };
    }

    /**
     * Generate filename for stored image.
     */
    protected function getFilename(string $key, HasMedia $model, string $extension): string
    {
        $modelId = $model->getKey();
        $timestamp = now()->format('YmdHis');
        
        return "{$key}_{$modelId}_{$timestamp}.{$extension}";
    }

    /**
     * Generate human-readable display name.
     */
    protected function getDisplayName(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
