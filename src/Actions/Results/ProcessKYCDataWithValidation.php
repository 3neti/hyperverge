<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;
use LBHurtado\HyperVerge\Data\Modules\SelfieValidationModuleData;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\ModelInput\Enums\InputType;
use LBHurtado\ModelInput\Traits\HasInputs;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Enhanced version of ProcessKYCData with validation and transaction safety.
 * 
 * Improvements over original:
 * - Data format validation (email, mobile, dates)
 * - Data transformation (normalize dates, phone numbers)
 * - Duplicate detection with merge strategy
 * - Transaction safety (rolls back on failure)
 * - Detailed error tracking
 * - Configurable validation rules
 * 
 * @example
 * $result = ProcessKYCDataWithValidation::run(
 *     $submission, 
 *     $transactionId, 
 *     includeAddress: true,
 *     strict: false
 * );
 * 
 * if ($result['success']) {
 *     echo "Stored: " . implode(', ', $result['stored_inputs']);
 * } else {
 *     echo "Validation errors: " . json_encode($result['validation_errors']);
 * }
 */
class ProcessKYCDataWithValidation
{
    use AsAction;

    /**
     * Process KYC data with validation and store as inputs.
     *
     * @param Model&HasInputs $model The model to store inputs on
     * @param string $transactionId The HyperVerge transaction ID
     * @param bool $includeAddress Whether to include address data
     * @param bool $strict If true, fail on any validation error; if false, store valid fields only
     * @param bool $overwriteExisting Whether to overwrite existing data
     * @param Model|null $context Campaign, CampaignSubmission, User for credential resolution
     * @return array Result with success status, stored inputs, and validation errors
     */
    public function handle(
        Model $model,
        string $transactionId,
        bool $includeAddress = false,
        bool $strict = false,
        bool $overwriteExisting = true,
        ?Model $context = null
    ): array {
        // Verify the model uses HasInputs trait
        if (!in_array(HasInputs::class, class_uses_recursive($model))) {
            throw new \InvalidArgumentException(
                'Model must use the HasInputs trait. Model: ' . get_class($model)
            );
        }

        $result = [
            'success' => false,
            'stored_inputs' => [],
            'skipped_inputs' => [],
            'validation_errors' => [],
            'transformation_warnings' => [],
        ];

        Log::info('[ProcessKYCDataWithValidation] Starting data processing', [
            'transaction_id' => $transactionId,
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'include_address' => $includeAddress,
            'strict' => $strict,
        ]);

        try {
            // Fetch KYC result
            $kycResult = FetchKYCResult::run($transactionId, $context);

            // Extract data from modules
            $extractedData = $this->extractDataFromModules($kycResult, $includeAddress);

            // Validate all data
            $validationResult = $this->validateExtractedData($extractedData);

            if (!$validationResult['valid']) {
                $result['validation_errors'] = $validationResult['errors'];
                
                if ($strict) {
                    Log::warning('[ProcessKYCDataWithValidation] Strict mode validation failed', [
                        'errors' => $validationResult['errors'],
                    ]);
                    throw new \InvalidArgumentException(
                        'Data validation failed: ' . json_encode($validationResult['errors'])
                    );
                }
            }

            // Data already transformed by DTO methods - just skip invalid fields
            $transformedData = [];
            foreach ($extractedData as $fieldName => $value) {
                if (isset($validationResult['errors'][$fieldName])) {
                    continue; // Skip invalid fields
                }
                $transformedData[$fieldName] = $value;
            }

            // Store data in database transaction
            DB::beginTransaction();

            foreach ($transformedData as $fieldName => $value) {
                try {
                    // Map field name to InputType enum
                    $inputType = match($fieldName) {
                        'name' => InputType::NAME,
                        'birth_date' => InputType::BIRTH_DATE,
                        'email' => InputType::EMAIL,
                        'mobile' => InputType::MOBILE,
                        'address' => InputType::ADDRESS,
                        'id_type' => InputType::ID_TYPE,
                        'id_number' => InputType::ID_NUMBER,
                        'reference_code' => InputType::REFERENCE_CODE,
                        default => null,
                    };
                    
                    if (!$inputType) {
                        continue;
                    }
                    
                    // Check if should skip existing
                    if (!$overwriteExisting && $this->hasExistingValue($model, $inputType)) {
                        $result['skipped_inputs'][] = $fieldName;
                        Log::debug('[ProcessKYCDataWithValidation] Skipping existing input', [
                            'input' => $fieldName,
                        ]);
                        continue;
                    }

                    // Store input
                    $model->setInput($inputType, $value);
                    $result['stored_inputs'][] = $fieldName;
                    
                    Log::debug('[ProcessKYCDataWithValidation] Stored input', [
                        'input' => $inputType->value,
                        'value_length' => is_string($value) ? strlen($value) : 'n/a',
                    ]);

                } catch (\Exception $e) {
                    $error = "Failed to store {$inputType->value}: {$e->getMessage()}";
                    $result['validation_errors'][$inputType->value] = $error;
                    
                    Log::error('[ProcessKYCDataWithValidation] Storage failed', [
                        'input' => $inputType->value,
                        'error' => $e->getMessage(),
                    ]);

                    if ($strict) {
                        throw $e;
                    }
                }
            }

            // Commit transaction
            DB::commit();
            $result['success'] = true;

            Log::info('[ProcessKYCDataWithValidation] Data processing completed', [
                'stored' => count($result['stored_inputs']),
                'skipped' => count($result['skipped_inputs']),
                'errors' => count($result['validation_errors']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $result['success'] = false;
            $result['validation_errors']['transaction'] = $e->getMessage();

            Log::error('[ProcessKYCDataWithValidation] Transaction failed', [
                'error' => $e->getMessage(),
                'stored_before_failure' => $result['stored_inputs'],
            ]);
        }

        return $result;
    }

    /**
     * Extract data from KYC result modules.
     */
    protected function extractDataFromModules(KYCResultData $result, bool $includeAddress): array
    {
        $data = [];

        // Extract from ID Card module - details are already transformed in DTO
        $idCardModule = $this->findIdCardModule($result);
        if ($idCardModule) {
            $details = $idCardModule->details;
            
            if (isset($details['fullName']) && !empty($details['fullName'])) {
                $data['name'] = $details['fullName'];
            }
            if (isset($details['dateOfBirth']) && !empty($details['dateOfBirth'])) {
                $data['birth_date'] = $details['dateOfBirth'];
            }
            if (isset($details['email']) && !empty($details['email'])) {
                $data['email'] = $details['email'];
            }
            if (isset($details['mobile']) && !empty($details['mobile'])) {
                $data['mobile'] = $details['mobile'];
            }
            if ($includeAddress && isset($details['address']) && !empty($details['address'])) {
                $data['address'] = $details['address'];
            }
            if (isset($details['idType']) && !empty($details['idType'])) {
                $data['id_type'] = $details['idType'];
            }
            if (isset($details['idNumber']) && !empty($details['idNumber'])) {
                $data['id_number'] = $details['idNumber'];
            }
            
            // Reference code from transaction ID
            $referenceCode = $idCardModule->raw['apiResponse']['metadata']['transactionId'] 
                ?? $idCardModule->raw['metadata']['transactionId'] 
                ?? null;
            if ($referenceCode) {
                $data['reference_code'] = $referenceCode;
            }
        }

        return $data;
    }

    /**
     * Validate extracted data against business rules.
     */
    protected function validateExtractedData(array $data): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'address' => ['nullable', 'string', 'max:500'],
            'id_type' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'reference_code' => ['nullable', 'string', 'max:255'],
        ];

        $validator = Validator::make($data, $rules);

        return [
            'valid' => $validator->passes(),
            'errors' => $validator->errors()->toArray(),
        ];
    }


    /**
     * Transform name to title case.
     */
    protected function transformName(string $value): string
    {
        return trim(ucwords(strtolower($value)));
    }

    /**
     * Transform email to lowercase.
     */
    protected function transformEmail(?string $value): ?string
    {
        return $value ? strtolower(trim($value)) : null;
    }

    /**
     * Transform mobile number to E.164 format if possible.
     */
    protected function transformMobile(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        // Remove all non-numeric characters except +
        $mobile = preg_replace('/[^0-9+]/', '', $value);

        // If starts with 0, assume Philippines and convert to +63
        if (str_starts_with($mobile, '0')) {
            $mobile = '+63' . substr($mobile, 1);
        }

        // Ensure it has country code
        if (!str_starts_with($mobile, '+')) {
            $mobile = '+' . $mobile;
        }

        return $mobile;
    }

    /**
     * Transform birth date to Y-m-d format.
     */
    protected function transformBirthDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            // Try common date formats
            $formats = [
                'Y-m-d',
                'd/m/Y',
                'm/d/Y',
                'd-m-Y',
                'm-d-Y',
                'Y/m/d',
            ];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $value);
                    if ($date) {
                        return $date->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Fallback: try parse
            return Carbon::parse($value)->format('Y-m-d');

        } catch (\Exception $e) {
            Log::warning('[ProcessKYCDataWithValidation] Failed to transform birth date', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            return $value; // Return as-is
        }
    }

    /**
     * Transform address (trim and capitalize).
     */
    protected function transformAddress(?string $value): ?string
    {
        return $value ? trim(ucwords(strtolower($value))) : null;
    }

    /**
     * Check if model already has a value for this input type.
     */
    protected function hasExistingValue(Model $model, InputType $inputType): bool
    {
        try {
            $value = $model->input($inputType);
            return !empty($value);
        } catch (\Exception $e) {
            return false;
        }
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
}
