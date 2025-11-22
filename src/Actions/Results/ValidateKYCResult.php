<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\HyperVerge\Data\Validation\KYCValidationResultData;
use LBHurtado\HyperVerge\Data\Modules\SelfieValidationModuleData;
use LBHurtado\HyperVerge\Enums\ApplicationStatus;

/**
 * Validate KYC result against business rules.
 * 
 * Applies configurable validation rules to determine if a KYC
 * verification result meets your business requirements.
 * 
 * @example
 * $result = FetchKYCResult::run($transactionId);
 * $validation = ValidateKYCResult::run($result);
 * 
 * if ($validation->valid) {
 *     // Approved!
 * } else {
 *     // Check $validation->reasons
 * }
 */
class ValidateKYCResult
{
    use AsAction;

    /**
     * Validate KYC result against business rules.
     */
    public function handle(KYCResultData $result): KYCValidationResultData
    {
        $reasons = [];
        $valid = true;

        // Simple rule: Trust HyperVerge - only reject explicitly rejected statuses
        // This accepts: approved, auto_approved, needs_review, etc.
        // This rejects: rejected, auto_declined, user_cancelled, error
        $rejectedStatuses = config('hyperverge.validation.rejected_statuses', ApplicationStatus::rejected());
        
        if (in_array($result->applicationStatus, $rejectedStatuses)) {
            $valid = false;
            $reasons[] = "Application status '{$result->applicationStatus}' indicates rejection";
        }

        return KYCValidationResultData::from([
            'valid' => $valid,
            'reasons' => $reasons,
            'status' => $result->applicationStatus,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get validation rules when used as a controller.
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string'],
        ];
    }

    /**
     * Handle as controller - fetch result first, then validate.
     */
    public function asController(string $transactionId): KYCValidationResultData
    {
        $result = FetchKYCResult::run($transactionId);
        return $this->handle($result);
    }

    /**
     * The command signature for artisan usage.
     */
    public string $commandSignature = 'hyperverge:validate 
                                        {transactionId : The transaction ID to validate}';

    /**
     * The command description.
     */
    public string $commandDescription = 'Validate a KYC result against business rules';

    /**
     * Handle as command.
     */
    public function asCommand(): int
    {
        $transactionId = $this->argument('transactionId');

        try {
            $result = FetchKYCResult::run($transactionId);
            $validation = $this->handle($result);

            if ($validation->valid) {
                $this->info('✅ KYC Result: VALID');
                $this->line('');
                $this->line('Status: ' . $validation->status);
                $this->line('Validated: ' . $validation->timestamp->format('Y-m-d H:i:s'));
                return self::SUCCESS;
            } else {
                $this->error('❌ KYC Result: INVALID');
                $this->line('');
                $this->line('Status: ' . $validation->status);
                $this->line('Reasons:');
                foreach ($validation->reasons as $reason) {
                    $this->line('  • ' . $reason);
                }
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
