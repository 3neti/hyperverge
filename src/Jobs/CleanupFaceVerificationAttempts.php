<?php

namespace LBHurtado\HyperVerge\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Job to cleanup old face verification attempts based on retention policy.
 * 
 * This job can be scheduled to run daily/weekly to maintain database hygiene
 * by deleting verification attempts older than the configured retention period.
 * 
 * @example
 * // In App\Console\Kernel.php schedule method:
 * $schedule->job(new CleanupFaceVerificationAttempts)->weekly();
 * 
 * // Or dispatch manually:
 * CleanupFaceVerificationAttempts::dispatch();
 */
class CleanupFaceVerificationAttempts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!config('hyperverge.face_verification.enabled', true)) {
            Log::info('[CleanupFaceVerificationAttempts] Face verification disabled, skipping cleanup');
            return;
        }

        $retentionDays = config('hyperverge.face_verification.attempts_retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        Log::info('[CleanupFaceVerificationAttempts] Starting cleanup', [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->toDateTimeString(),
        ]);

        // Find old verification attempts
        $oldAttempts = Media::where('collection_name', 'face_verification_attempts')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $count = $oldAttempts->count();

        if ($count === 0) {
            Log::info('[CleanupFaceVerificationAttempts] No old attempts to clean up');
            return;
        }

        // Delete old attempts
        foreach ($oldAttempts as $media) {
            try {
                $media->delete();
            } catch (\Exception $e) {
                Log::warning('[CleanupFaceVerificationAttempts] Failed to delete media', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[CleanupFaceVerificationAttempts] Cleanup completed', [
            'deleted_count' => $count,
        ]);
    }

    /**
     * Calculate the number of retries for this job.
     */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 min, 5 min, 15 min
    }
}
