<?php

namespace LBHurtado\HyperVerge\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when KYC images are successfully stored.
 * 
 * This event is dispatched after KYC verification images have been downloaded
 * and stored using Spatie Media Library.
 * 
 * Listen to this event to trigger image-related workflows like:
 * - Image processing (thumbnails, watermarks)
 * - Backup to external storage
 * - OCR or additional verification
 * - Updating image status
 * 
 * Usage:
 * 
 * // Listen in EventServiceProvider
 * protected $listen = [
 *     KYCImagesStored::class => [
 *         GenerateImageThumbnails::class,
 *         BackupImagesToS3::class,
 *     ],
 * ];
 * 
 * // Or listen inline
 * Event::listen(function (KYCImagesStored $event) {
 *     Log::info('KYC images stored', [
 *         'model' => get_class($event->model),
 *         'model_id' => $event->model->getKey(),
 *         'image_count' => count($event->mediaItems),
 *     ]);
 * });
 */
class KYCImagesStored
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model that the images are attached to
     * @param array $mediaItems Array of stored Media items
     * @param string $transactionId The KYC transaction ID
     */
    public function __construct(
        public Model $model,
        public array $mediaItems,
        public string $transactionId,
    ) {
    }

    /**
     * Get the channels the event should broadcast on (if needed).
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
