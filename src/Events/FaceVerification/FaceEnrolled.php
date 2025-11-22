<?php

namespace LBHurtado\HyperVerge\Events\FaceVerification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Event fired when a face is enrolled (reference selfie stored).
 */
class FaceEnrolled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public Media $media,
        public bool $livenessChecked
    ) {
    }
}
