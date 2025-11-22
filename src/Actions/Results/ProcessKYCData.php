<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;
use LBHurtado\HyperVerge\Data\Modules\SelfieValidationModuleData;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\ModelInput\Enums\InputType;
use LBHurtado\ModelInput\Traits\HasInputs;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Extract KYC data from HyperVerge result and store as model inputs.
 * 
 * This action processes KYC verification results and stores relevant data
 * as structured inputs on the given model using the HasInputs trait.
 * 
 * Usage:
 * 
 * // As an action
 * ProcessKYCData::run($user, $transactionId);
 * 
 * // With options
 * ProcessKYCData::run($user, $transactionId, includeAddress: true);
 * 
 * // As a job (queued)
 * ProcessKYCData::dispatch($user, $transactionId);
 * 
 * // As a controller
 * Route::post('/kyc/process', ProcessKYCData::class);
 * 
 * // As an artisan command
 * php artisan hyperverge:process-data {transactionId} --user={userId}
 */
class ProcessKYCData
{
    use AsAction;

    public string $commandSignature = 'hyperverge:process-data 
                                        {transactionId : The KYC transaction ID}
                                        {--user= : The user ID to associate data with}
                                        {--model= : The fully qualified model class name}
                                        {--include-address : Include address data}';

    public string $commandDescription = 'Process KYC data and store as model inputs';

    /**
     * Process KYC data and store as inputs on the given model.
     *
     * @param Model&HasInputs $model The model to store inputs on (must use HasInputs trait)
     * @param string $transactionId The HyperVerge transaction ID
     * @param bool $includeAddress Whether to include address data
     * @return array Array of stored input names
     * @throws \Exception
     */
    public function handle(
        Model $model,
        string $transactionId,
        bool $includeAddress = false
    ): array {
        // Verify the model uses HasInputs trait
        if (!in_array(HasInputs::class, class_uses_recursive($model))) {
            throw new \InvalidArgumentException(
                'Model must use the HasInputs trait. Model: ' . get_class($model)
            );
        }

        // Fetch KYC result
        Log::info('Processing KYC data', [
            'transaction_id' => $transactionId,
            'model' => get_class($model),
            'model_id' => $model->getKey(),
        ]);

        $result = FetchKYCResult::run($transactionId);
        
        $storedInputs = [];

        // Process ID Card module
        $idCardModule = $this->findIdCardModule($result);
        if ($idCardModule) {
            $storedInputs = array_merge(
                $storedInputs,
                $this->processIdCardData($model, $idCardModule, $includeAddress)
            );
        }

        // Process Selfie module for additional data if needed
        $selfieModule = $this->findSelfieModule($result);
        if ($selfieModule) {
            // Currently no specific fields to extract from selfie module
            // But structure is here for future expansion
        }

        Log::info('KYC data processed', [
            'transaction_id' => $transactionId,
            'stored_inputs' => $storedInputs,
        ]);

        return $storedInputs;
    }

    /**
     * Process ID card module data and store as inputs.
     */
    protected function processIdCardData(
        Model $model,
        IdCardModuleData $module,
        bool $includeAddress
    ): array {
        $storedInputs = [];
        $details = $module->details;
        
        // If details are empty, try extracting from nested apiResponse structure
        if (empty($details) && isset($module->raw['apiResponse']['result']['details'][0]['fieldsExtracted'])) {
            $fieldsExtracted = $module->raw['apiResponse']['result']['details'][0]['fieldsExtracted'];
            
            // Map HyperVerge field names to our structure
            $details = [
                'fullName' => $fieldsExtracted['fullName']['value'] ?? null,
                'dateOfBirth' => $fieldsExtracted['dateOfBirth']['value'] ?? null,
                'email' => $fieldsExtracted['email']['value'] ?? null,
                'mobile' => $fieldsExtracted['mobile']['value'] ?? $fieldsExtracted['mobileNumber']['value'] ?? null,
                'address' => $fieldsExtracted['address']['value'] ?? null,
            ];
        }

        // Map of detail keys to InputType enums
        $fieldMapping = [
            'fullName' => InputType::NAME,
            'dateOfBirth' => InputType::BIRTH_DATE,
            'address' => InputType::ADDRESS,
            'email' => InputType::EMAIL,
            'mobile' => InputType::MOBILE,
        ];

        foreach ($fieldMapping as $detailKey => $inputType) {
            // Skip address if not included
            if ($detailKey === 'address' && !$includeAddress) {
                continue;
            }

            $value = $details[$detailKey] ?? null;
            
            if ($value !== null && $value !== '') {
                try {
                    $model->setInput($inputType, $value);
                    $storedInputs[] = $inputType->value;
                    
                    Log::debug('Stored KYC input', [
                        'input' => $inputType->value,
                        'detail_key' => $detailKey,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to store KYC input', [
                        'input' => $inputType->value,
                        'detail_key' => $detailKey,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Store reference code (transaction ID) for tracking
        try {
            $transactionId = $module->raw['apiResponse']['metadata']['transactionId'] 
                ?? $module->raw['metadata']['transactionId'] 
                ?? '';
            if ($transactionId) {
                $model->setInput(InputType::REFERENCE_CODE, $transactionId);
                $storedInputs[] = InputType::REFERENCE_CODE->value;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to store reference code', [
                'error' => $e->getMessage(),
            ]);
        }

        return $storedInputs;
    }

    /**
     * Find the ID Card module from KYC result.
     */
    protected function findIdCardModule(KYCResultData $result): ?IdCardModuleData
    {
        foreach ($result->modules as $module) {
            if ($module instanceof IdCardModuleData) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Find the Selfie Validation module from KYC result.
     */
    protected function findSelfieModule(KYCResultData $result): ?SelfieValidationModuleData
    {
        foreach ($result->modules as $module) {
            if ($module instanceof SelfieValidationModuleData) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Handle the artisan command.
     */
    public function asCommand(Command $command): void
    {
        $transactionId = $command->argument('transactionId');
        $userId = $command->option('user');
        $modelClass = $command->option('model') ?? config('auth.providers.users.model', \App\Models\User::class);
        $includeAddress = $command->option('include-address');

        if (!$userId) {
            $command->error('User ID is required. Use --user option.');
            return;
        }

        // Load the model
        if (!class_exists($modelClass)) {
            $command->error("Model class not found: {$modelClass}");
            return;
        }

        $model = $modelClass::find($userId);
        if (!$model) {
            $command->error("Model not found with ID: {$userId}");
            return;
        }

        $command->info("Processing KYC data for transaction: {$transactionId}");
        $command->info("Model: {$modelClass} (ID: {$userId})");

        try {
            $storedInputs = $this->handle($model, $transactionId, $includeAddress);
            
            $command->info('KYC data processed successfully!');
            $command->table(
                ['Input Type'],
                array_map(fn($input) => [$input], $storedInputs)
            );
        } catch (\Exception $e) {
            $command->error('Failed to process KYC data: ' . $e->getMessage());
            Log::error('KYC data processing failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the controller request.
     */
    public function asController(): array
    {
        // Validate request
        $validated = request()->validate([
            'transaction_id' => 'required|string',
            'model_id' => 'required',
            'model_type' => 'sometimes|string',
            'include_address' => 'sometimes|boolean',
        ]);

        $modelClass = $validated['model_type'] ?? config('auth.providers.users.model', \App\Models\User::class);
        $model = $modelClass::findOrFail($validated['model_id']);

        $storedInputs = $this->handle(
            $model,
            $validated['transaction_id'],
            $validated['include_address'] ?? false
        );

        return [
            'success' => true,
            'transaction_id' => $validated['transaction_id'],
            'stored_inputs' => $storedInputs,
            'model' => [
                'type' => get_class($model),
                'id' => $model->getKey(),
            ],
        ];
    }

    /**
     * Get job tags for queue monitoring.
     */
    public function jobTags(Model $model, string $transactionId): array
    {
        return [
            'hyperverge',
            'kyc',
            'process-data',
            "transaction:{$transactionId}",
            "model:" . get_class($model),
            "model_id:{$model->getKey()}",
        ];
    }
}
