<?php

namespace LBHurtado\HyperVerge\Actions\FaceVerification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Data\Responses\FaceVerificationResultData;
use LBHurtado\HyperVerge\Services\FaceMatchService;
use LBHurtado\HyperVerge\Services\SelfieLivenessService;
use Exception;

/**
 * Verify a face against a stored reference selfie.
 * 
 * This action performs liveness check and face matching to verify
 * that the incoming selfie matches the enrolled reference.
 * 
 * @example
 * $result = VerifyFace::run(
 *     model: $user,
 *     selfie: $request->file('selfie'),
 *     checkLiveness: true,
 *     storeAttempt: true,
 *     context: ['action' => 'login', 'ip' => $request->ip()]
 * );
 * 
 * if ($result->verified) {
 *     // Verification successful
 * }
 */
class VerifyFace
{
    use AsAction;

    public string $commandSignature = 'hyperverge:verify-face 
                                        {model : The model class}
                                        {id : The model ID}
                                        {selfie : Path to selfie image}
                                        {--no-liveness : Skip liveness check}
                                        {--no-store : Skip storing attempt}';

    public string $commandDescription = 'Verify a face against stored reference';

    /**
     * Execute the verification action.
     *
     * @param Model $model The model to verify against
     * @param string|UploadedFile $selfie Selfie image to verify
     * @param bool $checkLiveness Whether to verify liveness
     * @param bool $storeAttempt Whether to store verification attempt
     * @param array $context Additional context (action, IP, etc.)
     * @return FaceVerificationResultData The verification result
     * @throws Exception
     */
    public function handle(
        Model $model,
        string|UploadedFile $selfie,
        bool $checkLiveness = true,
        bool $storeAttempt = true,
        array $context = []
    ): FaceVerificationResultData {
        // Check model has reference selfie
        if (!method_exists($model, 'getFirstMedia')) {
            throw new Exception(
                'Model must implement Spatie\MediaLibrary\HasMedia interface'
            );
        }

        $referenceSelfie = $model->getFirstMedia('face_reference_selfies');
        if (!$referenceSelfie) {
            Log::warning('[VerifyFace] No reference selfie found', [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
            ]);

            return FaceVerificationResultData::failed('No reference selfie enrolled');
        }

        try {
            // Convert selfie to base64
            $selfieBase64 = $this->toBase64($selfie);

            // Step 1: Liveness check (if required)
            $livenessResult = null;
            if ($checkLiveness && config('hyperverge.face_verification.require_liveness', true)) {
                $livenessResult = $this->performLivenessCheck($selfieBase64);
            }

            // Step 2: Face matching
            $faceMatchResult = $this->performFaceMatch($referenceSelfie, $selfieBase64);

            // Create result
            $result = FaceVerificationResultData::fromVerification(
                $livenessResult,
                $faceMatchResult,
                $context
            );

            // Store attempt if requested
            if ($storeAttempt && config('hyperverge.face_verification.store_verification_attempts', true)) {
                $this->storeAttempt($model, $selfie, $result, $context);
            }

            // Log result
            Log::info('[VerifyFace] Verification completed', [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'verified' => $result->verified,
                'liveness_check' => $result->livenessCheck,
                'face_match' => $result->faceMatch,
                'confidence' => $result->matchConfidence,
            ]);

            // Dispatch event
            if ($result->verified) {
                event(new \LBHurtado\HyperVerge\Events\FaceVerification\FaceVerificationSucceeded(
                    $model,
                    $result,
                    $context
                ));
            } else {
                event(new \LBHurtado\HyperVerge\Events\FaceVerification\FaceVerificationFailed(
                    $model,
                    $result,
                    $context
                ));
            }

            return $result;
        } catch (Exception $e) {
            Log::error('[VerifyFace] Verification failed with exception', [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);

            $result = FaceVerificationResultData::failed($e->getMessage(), $context);

            event(new \LBHurtado\HyperVerge\Events\FaceVerification\FaceVerificationFailed(
                $model,
                $result,
                $context
            ));

            return $result;
        }
    }

    /**
     * Convert selfie to base64.
     */
    protected function toBase64(string|UploadedFile $selfie): string
    {
        if ($selfie instanceof UploadedFile) {
            return base64_encode(file_get_contents($selfie->getRealPath()));
        }

        // Remove data URI prefix if present
        if (str_starts_with($selfie, 'data:image')) {
            $selfie = preg_replace('/^data:image\/\w+;base64,/', '', $selfie);
        }

        return $selfie;
    }

    /**
     * Perform liveness check.
     */
    protected function performLivenessCheck(string $base64): ?\LBHurtado\HyperVerge\Data\Responses\SelfieLivenessResponseData
    {
        try {
            $livenessService = app(SelfieLivenessService::class);
            $result = $livenessService->verify($base64);

            $minScore = config('hyperverge.face_verification.min_liveness_score', 0.8);
            $actualScore = $result->quality['livenessScore'] ?? 0.0;

            if ($actualScore < $minScore) {
                Log::warning('[VerifyFace] Liveness score below threshold', [
                    'actual' => $actualScore,
                    'minimum' => $minScore,
                ]);
            }

            return $result;
        } catch (\LBHurtado\HyperVerge\Exceptions\LivelinessFailedException $e) {
            Log::warning('[VerifyFace] Liveness check failed', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Liveness check failed');
        }
    }

    /**
     * Perform face matching.
     */
    protected function performFaceMatch(
        \Spatie\MediaLibrary\MediaCollections\Models\Media $referenceSelfie,
        string $selfieBase64
    ): \LBHurtado\HyperVerge\Data\Responses\FaceMatchResponseData {
        $faceMatchService = app(FaceMatchService::class);

        // Get reference image as base64
        $referenceBase64 = base64_encode(file_get_contents($referenceSelfie->getPath()));

        $result = $faceMatchService->match($referenceBase64, $selfieBase64);

        $minConfidence = config('hyperverge.face_verification.min_match_confidence', 0.85);

        if ($result->confidence < $minConfidence) {
            Log::warning('[VerifyFace] Face match confidence below threshold', [
                'actual' => $result->confidence,
                'minimum' => $minConfidence,
            ]);
        }

        return $result;
    }

    /**
     * Store verification attempt.
     */
    protected function storeAttempt(
        Model $model,
        string|UploadedFile $selfie,
        FaceVerificationResultData $result,
        array $context
    ): void {
        try {
            $mediaAdder = $selfie instanceof UploadedFile
                ? $model->addMedia($selfie)
                : $model->addMediaFromBase64($selfie);

            $mediaAdder
                ->withCustomProperties(array_merge($context, [
                    'verification_result' => [
                        'verified' => $result->verified,
                        'liveness_check' => $result->livenessCheck,
                        'liveness_score' => $result->livenessScore,
                        'face_match' => $result->faceMatch,
                        'match_confidence' => $result->matchConfidence,
                        'failure_reason' => $result->failureReason,
                    ],
                    'verified_at' => $result->timestamp,
                    'type' => 'face_verification_attempt',
                ]))
                ->toMediaCollection('face_verification_attempts');
        } catch (Exception $e) {
            Log::warning('[VerifyFace] Failed to store attempt', [
                'error' => $e->getMessage(),
            ]);
            // Don't fail verification if we can't store attempt
        }
    }

    /**
     * Handle as artisan command.
     */
    public function asCommand(): int
    {
        $modelClass = $this->argument('model');
        $id = $this->argument('id');
        $selfiePath = $this->argument('selfie');
        $checkLiveness = !$this->option('no-liveness');
        $storeAttempt = !$this->option('no-store');

        try {
            if (!class_exists($modelClass)) {
                $this->error("Model class not found: {$modelClass}");
                return self::FAILURE;
            }

            $model = $modelClass::findOrFail($id);

            if (!file_exists($selfiePath)) {
                $this->error("Selfie file not found: {$selfiePath}");
                return self::FAILURE;
            }

            $uploadedFile = new UploadedFile(
                $selfiePath,
                basename($selfiePath),
                mime_content_type($selfiePath),
                null,
                true
            );

            $result = $this->handle($model, $uploadedFile, $checkLiveness, $storeAttempt);

            if ($result->verified) {
                $this->info('✅ Face verification PASSED!');
            } else {
                $this->error('❌ Face verification FAILED!');
            }

            $this->line('');
            $this->line('Model: ' . get_class($model) . ' #' . $model->getKey());
            $this->line('Verified: ' . ($result->verified ? 'Yes' : 'No'));
            $this->line('Liveness Check: ' . ($result->livenessCheck ? 'Passed' : 'Skipped/Failed'));
            
            if ($result->livenessScore !== null) {
                $this->line('Liveness Score: ' . number_format($result->livenessScore, 2));
            }
            
            $this->line('Face Match: ' . ($result->faceMatch ? 'Yes' : 'No'));
            $this->line('Match Confidence: ' . number_format($result->matchConfidence, 2));
            
            if ($result->failureReason) {
                $this->line('Failure Reason: ' . $result->failureReason);
            }

            return $result->verified ? self::SUCCESS : self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Verification error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
