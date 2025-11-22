<?php

namespace LBHurtado\HyperVerge\Events\FaceVerification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Event fired when a reference selfie is updated.
 */
class ReferenceSelfieUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public Media $newMedia,
        public bool $wasVerified
    ) {
    }
}
