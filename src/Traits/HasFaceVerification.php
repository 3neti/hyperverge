<?php

namespace LBHurtado\HyperVerge\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use LBHurtado\HyperVerge\Actions\FaceVerification\EnrollFace;
use LBHurtado\HyperVerge\Actions\FaceVerification\VerifyFace;
use LBHurtado\HyperVerge\Data\Responses\FaceVerificationResultData;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Add face verification capabilities to any model.
 * 
 * This trait provides methods to enroll a reference selfie and verify
 * subsequent selfies against it for authentication purposes.
 * 
 * Requirements:
 * - Model must implement Spatie\MediaLibrary\HasMedia
 * - Model must use Spatie\MediaLibrary\InteractsWithMedia trait
 * - Model must register media collections in registerMediaCollections()
 * 
 * @example
 * class User extends Model implements HasMedia
 * {
 *     use InteractsWithMedia, HasFaceVerification;
 *     
 *     public function registerMediaCollections(): void
 *     {
 *         $this->addMediaCollection('face_reference_selfies')->singleFile();
 *         $this->addMediaCollection('face_verification_attempts');
 *     }
 * }
 * 
 * // Enroll a face
 * $user->enrollFace($request->file('selfie'));
 * 
 * // Verify a face
 * $result = $user->verifyFace($request->file('selfie'), context: ['action' => 'login']);
 * if ($result->verified) {
 *     // Success
 * }
 */
trait HasFaceVerification
{
    /**
     * Enroll a face by storing a reference selfie.
     *
     * @param string|UploadedFile $selfie Selfie image to store as reference
     * @param bool $checkLiveness Whether to verify liveness before storing
     * @param array $metadata Additional metadata to store with the image
     * @return Media The stored media object
     * @throws \Exception
     */
    public function enrollFace(
        string|UploadedFile $selfie,
        bool $checkLiveness = true,
        array $metadata = []
    ): Media {
        return EnrollFace::run(
            model: $this,
            selfie: $selfie,
            checkLiveness: $checkLiveness,
            metadata: $metadata
        );
    }

    /**
     * Verify a face against the stored reference selfie.
     *
     * @param string|UploadedFile $selfie Selfie image to verify
     * @param bool $checkLiveness Whether to verify liveness
     * @param bool $storeAttempt Whether to store verification attempt
     * @param array $context Additional context (action type, IP, etc.)
     * @return FaceVerificationResultData The verification result
     */
    public function verifyFace(
        string|UploadedFile $selfie,
        bool $checkLiveness = true,
        bool $storeAttempt = true,
        array $context = []
    ): FaceVerificationResultData {
        return VerifyFace::run(
            model: $this,
            selfie: $selfie,
            checkLiveness: $checkLiveness,
            storeAttempt: $storeAttempt,
            context: $context
        );
    }

    /**
     * Get the current reference selfie.
     *
     * @return Media|null The reference selfie media object
     */
    public function getReferenceSelfie(): ?Media
    {
        return $this->getFirstMedia('face_reference_selfies');
    }

    /**
     * Check if model has a reference selfie enrolled.
     *
     * @return bool True if reference selfie exists
     */
    public function hasReferenceSelfie(): bool
    {
        return $this->hasMedia('face_reference_selfies');
    }

    /**
     * Update the reference selfie.
     * 
     * This will replace the existing reference selfie with a new one.
     * Optionally requires verification against the old selfie first.
     *
     * @param string|UploadedFile $selfie New selfie image
     * @param bool $requireVerification Verify against old selfie first
     * @param bool $checkLiveness Whether to verify liveness
     * @param array $metadata Additional metadata
     * @return Media The new reference selfie media object
     * @throws \Exception
     */
    public function updateReferenceSelfie(
        string|UploadedFile $selfie,
        bool $requireVerification = true,
        bool $checkLiveness = true,
        array $metadata = []
    ): Media {
        // If verification required, verify new selfie matches old one
        if ($requireVerification && $this->hasReferenceSelfie()) {
            $verificationResult = $this->verifyFace(
                selfie: $selfie,
                checkLiveness: $checkLiveness,
                storeAttempt: false
            );

            if (!$verificationResult->verified) {
                throw new \Exception(
                    'New selfie does not match existing reference: ' . 
                    $verificationResult->failureReason
                );
            }
        }

        // Archive old reference selfie
        if ($this->hasReferenceSelfie()) {
            $oldReference = $this->getReferenceSelfie();
            
            // Move to archive collection
            $oldReference->copy($this, 'face_reference_selfies_archive');
        }

        // Enroll new reference
        $media = $this->enrollFace(
            selfie: $selfie,
            checkLiveness: $checkLiveness,
            metadata: array_merge($metadata, [
                'updated_at' => now()->toIso8601String(),
                'reason' => 'reference_update',
            ])
        );

        // Dispatch event
        event(new \LBHurtado\HyperVerge\Events\FaceVerification\ReferenceSelfieUpdated(
            $this,
            $media,
            $requireVerification
        ));

        return $media;
    }

    /**
     * Get all face verification attempts.
     *
     * @return Collection Collection of Media objects
     */
    public function getFaceVerificationAttempts(): Collection
    {
        return $this->getMedia('face_verification_attempts');
    }

    /**
     * Clear old face verification attempts.
     * 
     * This deletes verification attempts older than the configured retention period.
     *
     * @param int|null $daysToKeep Number of days to keep (null = use config)
     * @return int Number of attempts deleted
     */
    public function clearFaceVerificationAttempts(?int $daysToKeep = null): int
    {
        $daysToKeep = $daysToKeep ?? config('hyperverge.face_verification.attempts_retention_days', 30);
        
        $cutoffDate = now()->subDays($daysToKeep);
        
        $oldAttempts = $this->getMedia('face_verification_attempts')
            ->filter(function (Media $media) use ($cutoffDate) {
                return $media->created_at->isBefore($cutoffDate);
            });

        $count = $oldAttempts->count();
        
        foreach ($oldAttempts as $media) {
            $media->delete();
        }

        return $count;
    }

    /**
     * Get recent verification attempts.
     *
     * @param int $limit Maximum number of attempts to return
     * @return Collection Collection of Media objects
     */
    public function getRecentVerificationAttempts(int $limit = 10): Collection
    {
        return $this->getMedia('face_verification_attempts')
            ->sortByDesc('created_at')
            ->take($limit);
    }

    /**
     * Get successful verification attempts.
     *
     * @return Collection Collection of Media objects
     */
    public function getSuccessfulVerificationAttempts(): Collection
    {
        return $this->getMedia('face_verification_attempts')
            ->filter(function (Media $media) {
                return $media->getCustomProperty('verification_result.verified', false);
            });
    }

    /**
     * Get failed verification attempts.
     *
     * @return Collection Collection of Media objects
     */
    public function getFailedVerificationAttempts(): Collection
    {
        return $this->getMedia('face_verification_attempts')
            ->filter(function (Media $media) {
                return !$media->getCustomProperty('verification_result.verified', false);
            });
    }

    /**
     * Get verification statistics.
     *
     * @return array Statistics about verification attempts
     */
    public function getFaceVerificationStats(): array
    {
        $attempts = $this->getFaceVerificationAttempts();
        $successful = $this->getSuccessfulVerificationAttempts();
        $failed = $this->getFailedVerificationAttempts();

        return [
            'total_attempts' => $attempts->count(),
            'successful' => $successful->count(),
            'failed' => $failed->count(),
            'success_rate' => $attempts->count() > 0 
                ? round(($successful->count() / $attempts->count()) * 100, 2) 
                : 0,
            'last_attempt_at' => $attempts->sortByDesc('created_at')->first()?->created_at,
            'last_successful_at' => $successful->sortByDesc('created_at')->first()?->created_at,
            'has_reference_selfie' => $this->hasReferenceSelfie(),
            'reference_enrolled_at' => $this->getReferenceSelfie()?->created_at,
        ];
    }
}
