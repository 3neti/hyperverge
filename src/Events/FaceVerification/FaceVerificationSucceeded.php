<?php

namespace LBHurtado\HyperVerge\Events\FaceVerification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\HyperVerge\Data\Responses\FaceVerificationResultData;

/**
 * Event fired when face verification succeeds.
 */
class FaceVerificationSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public FaceVerificationResultData $result,
        public array $context
    ) {
    }
}
