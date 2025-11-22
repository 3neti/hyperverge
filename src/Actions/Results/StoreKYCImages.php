<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Events\KYCImagesStored;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Store KYC images using Spatie Media Library.
 * 
 * Downloads images from HyperVerge S3 URLs and stores them
 * using Laravel Media Library with automatic conversions.
 * 
 * @example
 * // Get image URLs
 * $imageUrls = ExtractKYCImages::run($transactionId);
 * 
 * // Store on model (User must implement HasMedia)
 * $media = StoreKYCImages::run($user, $imageUrls);
 * 
 * // Access images later
 * $user->getFirstMediaUrl('kyc_id_cards');
 * $user->getFirstMediaUrl('kyc_id_cards', 'thumb');
 */
class StoreKYCImages
{
    use AsAction;

    /**
     * Store KYC images using Spatie Media Library.
     * 
     * @param HasMedia $model The model that implements HasMedia (e.g. User)
     * @param array $imageUrls Array of image URLs from HyperVerge
     * @param string|null $transactionId Optional transaction ID for event dispatch
     * @return array Array of stored media items
     */
    public function handle(HasMedia $model, array $imageUrls, ?string $transactionId = null): array
    {
        $storedMedia = [];
        $timeout = config('hyperverge.images.timeout', 30);
        $maxRetries = config('hyperverge.images.max_retries', 3);

        foreach ($imageUrls as $key => $url) {
            try {
                Log::info('[StoreKYCImages] Downloading image', [
                    'key' => $key,
                    'url' => $url,
                ]);

                // Download image from HyperVerge
                $response = Http::timeout($timeout)
                    ->retry($maxRetries, 200)
                    ->get($url);

                if ($response->successful()) {
                    // Determine collection and filename
                    $collection = $this->getCollection($key);
                    $filename = $this->getFilename($key, $model);
                    $name = $this->getDisplayName($key);

                    // Add to media library
                    $media = $model
                        ->addMediaFromString($response->body())
                        ->usingFileName($filename)
                        ->usingName($name)
                        ->withCustomProperties([
                            'kyc_image_type' => $key,
                            'original_url' => $url,
                            'downloaded_at' => now()->toIso8601String(),
                        ])
                        ->toMediaCollection($collection);

                    $storedMedia[$key] = $media;

                    Log::info('[StoreKYCImages] Image stored successfully', [
                        'key' => $key,
                        'media_id' => $media->id,
                        'collection' => $collection,
                        'size' => $media->size,
                    ]);
                } else {
                    Log::error('[StoreKYCImages] Failed to download image', [
                        'key' => $key,
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('[StoreKYCImages] Exception while storing image', [
                    'key' => $key,
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Dispatch event if images were stored and we have a transaction ID
        if (!empty($storedMedia) && $transactionId) {
            KYCImagesStored::dispatch($model, $storedMedia, $transactionId);
        }

        return $storedMedia;
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
    protected function getFilename(string $key, HasMedia $model): string
    {
        $modelId = $model->getKey();
        $timestamp = now()->format('YmdHis');
        $extension = $this->getExtension($key);
        
        return "{$key}_{$modelId}_{$timestamp}.{$extension}";
    }

    /**
     * Get file extension (defaults to jpg).
     */
    protected function getExtension(string $key): string
    {
        // Most KYC images are JPEG
        return 'jpg';
    }

    /**
     * Generate human-readable display name.
     */
    protected function getDisplayName(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * The command signature for artisan usage.
     */
    public string $commandSignature = 'hyperverge:store-images 
                                        {transactionId : The transaction ID}
                                        {--user= : User ID to attach images to}';

    /**
     * The command description.
     */
    public string $commandDescription = 'Download and store KYC images from HyperVerge';

    /**
     * Handle as command.
     */
    public function asCommand(): int
    {
        $transactionId = $this->argument('transactionId');
        $userId = $this->option('user');

        if (!$userId) {
            $this->error('--user option is required');
            return self::FAILURE;
        }

        try {
            // Find user
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                $this->error("User {$userId} not found");
                return self::FAILURE;
            }

            if (!$user instanceof HasMedia) {
                $this->error('User model must implement HasMedia interface');
                return self::FAILURE;
            }

            // Extract image URLs
            $imageUrls = ExtractKYCImages::run($transactionId);

            if (empty($imageUrls)) {
                $this->warn('No images found for transaction');
                return self::SUCCESS;
            }

            $this->info("Found " . count($imageUrls) . " images to download");

            // Store images
            $media = $this->handle($user, $imageUrls);

            $this->info("✅ Stored " . count($media) . " images successfully");
            
            foreach ($media as $key => $mediaItem) {
                $this->line("  • {$key}: " . $mediaItem->file_name);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
