<?php

namespace LBHurtado\HyperVerge\Actions;

use LBHurtado\HyperVerge\Data\Responses\FaceMatchResponseData;
use LBHurtado\HyperVerge\Data\Responses\SelfieLivenessResponseData;
use LBHurtado\HyperVerge\Services\FaceMatchService;
use LBHurtado\HyperVerge\Services\SelfieLivenessService;

/**
 * Pipeline action that verifies selfie liveness and performs face matching.
 */
class VerifySelfieAndMatch
{
    public function __construct(
        protected SelfieLivenessService $livenessService,
        protected FaceMatchService $faceMatchService,
    ) {
    }

    public function execute(string $referenceImage, string $selfieImage): array
    {
        // Step 1: Verify selfie liveness
        $livenessResult = $this->livenessService->verify($selfieImage);

        // Step 2: Perform face match
        $matchResult = $this->faceMatchService->match($referenceImage, $selfieImage);

        return [
            'liveness' => $livenessResult,
            'match' => $matchResult,
            'success' => $livenessResult->isLive && $matchResult->isMatch,
        ];
    }

    public function executeSafe(string $referenceImage, string $selfieImage): array
    {
        try {
            return $this->execute($referenceImage, $selfieImage);
        } catch (\Exception $e) {
            return [
                'liveness' => null,
                'match' => null,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
