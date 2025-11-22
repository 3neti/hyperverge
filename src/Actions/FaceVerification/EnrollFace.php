<?php

namespace LBHurtado\HyperVerge\Actions\FaceVerification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Services\SelfieLivenessService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Exception;

/**
 * Enroll a face by storing a reference selfie for a model.
 * 
 * This action validates and stores a reference selfie that will be used
 * for future face verification attempts.
 * 
 * @example
 * $media = EnrollFace::run(
 *     model: $user,
 *     selfie: $request->file('selfie'),
 *     checkLiveness: true,
 *     metadata: ['enrolled_at' => now(), 'ip' => $request->ip()]
 * );
 */
class EnrollFace
{
    use AsAction;

    public string $commandSignature = 'hyperverge:enroll-face 
                                        {model : The model class}
                                        {id : The model ID}
                                        {selfie : Path to selfie image}
                                        {--no-liveness : Skip liveness check}';

    public string $commandDescription = 'Enroll a face for verification';

    /**
     * Execute the enrollment action.
     *
     * @param Model $model The model to enroll (must implement HasMedia)
     * @param string|UploadedFile $selfie Selfie image to store as reference
     * @param bool $checkLiveness Whether to verify liveness before storing
     * @param array $metadata Additional metadata to store with the image
     * @return Media The stored media object
     * @throws Exception
     */
    public function handle(
        Model $model,
        string|UploadedFile $selfie,
        bool $checkLiveness = true,
        array $metadata = []
    ): Media {
        // Validate model implements HasMedia
        if (!method_exists($model, 'addMedia')) {
            throw new Exception(
                'Model must implement Spatie\MediaLibrary\HasMedia interface'
            );
        }

        // Validate the selfie image
        $this->validateSelfie($selfie);

        // Check liveness if required
        if ($checkLiveness) {
            $this->checkLiveness($selfie);
        }

        // Remove existing reference selfies (only keep latest)
        $model->clearMediaCollection('face_reference_selfies');

        // Store the new reference selfie
        $media = $this->storeSelfie($model, $selfie, $metadata);

        Log::info('[EnrollFace] Face enrolled successfully', [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'media_id' => $media->id,
            'liveness_checked' => $checkLiveness,
        ]);

        // Dispatch event
        event(new \LBHurtado\HyperVerge\Events\FaceVerification\FaceEnrolled(
            $model,
            $media,
            $checkLiveness
        ));

        return $media;
    }

    /**
     * Validate the selfie image.
     *
     * @throws Exception
     */
    protected function validateSelfie(string|UploadedFile $selfie): void
    {
        $config = config('hyperverge.face_verification', []);

        if ($selfie instanceof UploadedFile) {
            $validator = Validator::make(
                ['selfie' => $selfie],
                [
                    'selfie' => [
                        'required',
                        'file',
                        'image',
                        'mimes:' . implode(',', $config['allowed_mime_types'] ?? ['jpeg', 'png', 'jpg']),
                        'max:' . ($config['max_file_size'] ?? 5120), // KB
                    ],
                ]
            );

            if ($validator->fails()) {
                throw new Exception(
                    'Invalid selfie image: ' . $validator->errors()->first()
                );
            }

            // Validate dimensions
            $image = getimagesize($selfie->getRealPath());
            if ($image) {
                $minWidth = $config['min_width'] ?? 200;
                $minHeight = $config['min_height'] ?? 200;

                if ($image[0] < $minWidth || $image[1] < $minHeight) {
                    throw new Exception(
                        "Image dimensions too small. Minimum: {$minWidth}x{$minHeight}px"
                    );
                }
            }
        } elseif (is_string($selfie)) {
            // Validate base64 string
            if (!preg_match('/^data:image\/(jpeg|png|jpg);base64,/', $selfie)) {
                throw new Exception('Invalid base64 image format');
            }
        }
    }

    /**
     * Check liveness of the selfie.
     *
     * @throws Exception
     */
    protected function checkLiveness(string|UploadedFile $selfie): void
    {
        $livenessService = app(SelfieLivenessService::class);

        // Convert to base64 if needed
        $base64 = $selfie instanceof UploadedFile
            ? base64_encode(file_get_contents($selfie->getRealPath()))
            : $selfie;

        try {
            $result = $livenessService->verify($base64);

            $minScore = config('hyperverge.face_verification.min_liveness_score', 0.8);
            $actualScore = $result->quality['livenessScore'] ?? 0.0;

            if ($actualScore < $minScore) {
                throw new Exception(
                    "Liveness score too low: {$actualScore} (minimum: {$minScore})"
                );
            }

            Log::debug('[EnrollFace] Liveness check passed', [
                'score' => $actualScore,
                'is_live' => $result->isLive,
            ]);
        } catch (\LBHurtado\HyperVerge\Exceptions\LivelinessFailedException $e) {
            throw new Exception(
                'Liveness check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Store the selfie as reference.
     */
    protected function storeSelfie(
        Model $model,
        string|UploadedFile $selfie,
        array $metadata
    ): Media {
        $mediaAdder = $selfie instanceof UploadedFile
            ? $model->addMedia($selfie)
            : $model->addMediaFromBase64($selfie);

        return $mediaAdder
            ->withCustomProperties(array_merge($metadata, [
                'enrolled_at' => now()->toIso8601String(),
                'type' => 'face_reference',
            ]))
            ->toMediaCollection('face_reference_selfies');
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

            $media = $this->handle($model, $uploadedFile, $checkLiveness);

            $this->info('✅ Face enrolled successfully!');
            $this->line('');
            $this->line('Model: ' . get_class($model) . ' #' . $model->getKey());
            $this->line('Media ID: ' . $media->id);
            $this->line('Liveness checked: ' . ($checkLiveness ? 'Yes' : 'No'));

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Enrollment failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
