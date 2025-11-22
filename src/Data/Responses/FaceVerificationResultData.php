<?php

namespace LBHurtado\HyperVerge\Data\Responses;

use LBHurtado\HyperVerge\Data\HypervergeResponseData;

/**
 * Face verification result DTO.
 * 
 * Contains results of liveness check and face matching for verification.
 */
class FaceVerificationResultData extends HypervergeResponseData
{
    public function __construct(
        public bool $verified,
        public bool $livenessCheck,
        public ?float $livenessScore,
        public bool $faceMatch,
        public float $matchConfidence,
        public array $quality,
        public string $timestamp,
        public ?string $failureReason = null,
        array $raw = [],
        array $meta = [],
    ) {
        parent::__construct(raw: $raw, meta: $meta);
    }

    /**
     * Create from liveness and face match results.
     */
    public static function fromVerification(
        ?SelfieLivenessResponseData $liveness,
        ?FaceMatchResponseData $faceMatch,
        array $meta = []
    ): self {
        $livenessCheck = $liveness?->isLive ?? false;
        $faceMatchPassed = $faceMatch?->isMatch ?? false;
        $verified = $livenessCheck && $faceMatchPassed;

        $failureReason = null;
        if (!$verified) {
            if (!$livenessCheck) {
                $failureReason = 'Liveness check failed';
            } elseif (!$faceMatchPassed) {
                $failureReason = 'Face match failed';
            }
        }

        return new self(
            verified: $verified,
            livenessCheck: $livenessCheck,
            livenessScore: $liveness?->quality['livenessScore'] ?? null,
            faceMatch: $faceMatchPassed,
            matchConfidence: $faceMatch?->confidence ?? 0.0,
            quality: array_merge(
                $liveness?->quality ?? [],
                $faceMatch?->quality ?? []
            ),
            timestamp: now()->toIso8601String(),
            failureReason: $failureReason,
            raw: [
                'liveness' => $liveness?->raw ?? [],
                'face_match' => $faceMatch?->raw ?? [],
            ],
            meta: $meta,
        );
    }

    /**
     * Create from face match only (no liveness check).
     */
    public static function fromFaceMatchOnly(
        FaceMatchResponseData $faceMatch,
        array $meta = []
    ): self {
        return new self(
            verified: $faceMatch->isMatch,
            livenessCheck: false,
            livenessScore: null,
            faceMatch: $faceMatch->isMatch,
            matchConfidence: $faceMatch->confidence,
            quality: $faceMatch->quality,
            timestamp: now()->toIso8601String(),
            failureReason: $faceMatch->isMatch ? null : 'Face match failed',
            raw: ['face_match' => $faceMatch->raw],
            meta: $meta,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(string $reason, array $meta = []): self
    {
        return new self(
            verified: false,
            livenessCheck: false,
            livenessScore: null,
            faceMatch: false,
            matchConfidence: 0.0,
            quality: [],
            timestamp: now()->toIso8601String(),
            failureReason: $reason,
            raw: [],
            meta: $meta,
        );
    }
}
