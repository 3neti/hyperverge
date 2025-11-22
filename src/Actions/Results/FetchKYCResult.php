<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Factories\HypervergeClientFactory;
use LBHurtado\HyperVerge\Support\HypervergeClient;
use LBHurtado\HyperVerge\Data\Requests\FetchKYCResultRequestData;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;

/**
 * Fetch KYC verification results for a completed transaction.
 * 
 * This action retrieves the verification results from HyperVerge
 * and returns a typed DTO with all module data, images, and extracted information.
 * 
 * @example
 * $result = FetchKYCResult::run(transactionId: 'user_123_abc');
 * 
 * // Access typed data
 * echo $result->applicationStatus;
 * foreach ($result->modules as $module) {
 *     if ($module instanceof IdCardModuleData) {
 *         echo $module->countrySelected;
 *     }
 * }
 * 
 * @see https://documentation.hyperverge.co
 */
class FetchKYCResult
{
    use AsAction;

    /**
     * The command signature for artisan usage.
     */
    public string $commandSignature = 'hyperverge:fetch-result 
                                        {transactionId : The transaction ID to fetch}
                                        {--json : Output as JSON}';

    /**
     * The command description.
     */
    public string $commandDescription = 'Fetch KYC verification results from HyperVerge';

    /**
     * Execute the action to fetch KYC results.
     *
     * @param string $transactionId The transaction ID to fetch results for
     * @param Model|null $context Campaign, CampaignSubmission, User for credential resolution
     * @return KYCResultData Typed DTO containing all verification data
     */
    public function handle(string $transactionId, ?Model $context = null): KYCResultData
    {
        $factory = app(HypervergeClientFactory::class);
        $client = $factory->make($context);
        
        $request = FetchKYCResultRequestData::fromTransaction($transactionId);
        
        $response = $client->post('/link-kyc/results', $request->toPayload());
        
        return KYCResultData::fromHyperverge($response);
    }

    /**
     * Get validation rules when used as a controller.
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Map HTTP request data to action parameters when used as controller.
     */
    public function mapToParameters(array $data): array
    {
        return [
            'transactionId' => $data['transaction_id'],
        ];
    }

    /**
     * Handle the action as a job.
     */
    public function asJob(string $transactionId, ?Model $context = null): void
    {
        $this->handle($transactionId, $context);
    }

    /**
     * Handle the action as a command.
     */
    public function asCommand(): int
    {
        $transactionId = $this->argument('transactionId');
        $result = $this->handle($transactionId);

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $result->status,
                'status_code' => $result->statusCode,
                'transaction_id' => $result->transactionId,
                'application_status' => $result->applicationStatus,
                'modules_count' => count($result->modules),
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info('✅ KYC Results Retrieved Successfully!');
            $this->line('');
            $this->line('Transaction ID: ' . $result->transactionId);
            $this->line('Application Status: ' . $result->applicationStatus);
            $this->line('Modules: ' . count($result->modules));
            
            if (!empty($result->modules)) {
                $this->line('');
                $this->info('Modules:');
                foreach ($result->modules as $module) {
                    $this->line('  • ' . $module->module . ' (ID: ' . $module->moduleId . ')');
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Get the response data when used as a controller.
     */
    public function jsonResponse(KYCResultData $result): array
    {
        return [
            'success' => $result->status === 'success',
            'transaction_id' => $result->transactionId,
            'application_status' => $result->applicationStatus,
            'request_id' => $result->requestId,
            'modules' => array_map(function ($module) {
                return [
                    'module' => $module->module,
                    'module_id' => $module->moduleId,
                    'status' => $module->status,
                ];
            }, $result->modules),
        ];
    }
}
