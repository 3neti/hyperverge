<?php

namespace LBHurtado\HyperVerge\Actions\LinkKYC;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use LBHurtado\HyperVerge\Data\Requests\LinkKycOptionsData;
use LBHurtado\HyperVerge\Factories\HypervergeClientFactory;
use Exception;

/**
 * Generate an onboarding link from HyperVerge.
 * 
 * This action creates a Link KYC onboarding URL that users can visit
 * to complete their identity verification process.
 * 
 * Based on HyperVerge Link KYC API documentation:
 * https://documentation.hyperverge.co/onboard-links/
 * 
 * @example
 * // Simple usage - just get the URL
 * $url = GenerateOnboardingLink::get(
 *     transactionId: 'user_123_' . time(),
 *     redirectUrl: route('kyc.complete')
 * );
 * 
 * // With DTO options
 * $options = LinkKycOptionsData::withMobileAuth(
 *     mobileNumber: '+639171234567',
 *     redirectTime: '10'
 * );
 * $url = GenerateOnboardingLink::get(
 *     transactionId: 'user_123_' . time(),
 *     redirectUrl: route('kyc.complete'),
 *     options: $options
 * );
 * 
 * // With array options (backward compatible)
 * $response = GenerateOnboardingLink::run(
 *     transactionId: 'user_123_' . time(),
 *     workflowId: 'onboarding',
 *     redirectUrl: route('kyc.complete'),
 *     options: [
 *         'validateWorkflowInputs' => 'yes',
 *         'allowEmptyWorkflowInputs' => 'no',
 *     ]
 * );
 */
class GenerateOnboardingLink
{
    use AsAction;

    protected string $endpoint = '/link-kyc/start';

    /**
     * The command signature for artisan usage.
     */
    public string $commandSignature = 'hyperverge:generate-link 
                                        {transactionId : The transaction ID}
                                        {--w|workflow= : The workflow ID (default: from config)}
                                        {--r|redirect= : The redirect URL (default: from config)}';

    /**
     * The command description.
     */
    public string $commandDescription = 'Generate a HyperVerge onboarding link';

    /**
     * Execute the action and return full response.
     *
     * @param string $transactionId Unique identifier for this transaction
     * @param string|null $workflowId HyperVerge workflow ID (default: from config)
     * @param string|null $redirectUrl URL to redirect after completion (default: from config)
     * @param LinkKycOptionsData|array $options Additional API options (DTO or array)
     * @param Model|null $context Campaign, CampaignSubmission, User for credential resolution
     * @return array Full HyperVerge response
     * @throws Exception
     */
    public function handle(
        string $transactionId,
        ?string $workflowId = null,
        ?string $redirectUrl = null,
        LinkKycOptionsData|array $options = [],
        ?Model $context = null
    ): array {
        // Resolve credentials from context
        $factory = app(HypervergeClientFactory::class);
        $client = $factory->make($context);
        $credentials = app(\LBHurtado\HyperVerge\Contracts\CredentialResolverInterface::class)->resolve($context);
        
        $workflowId = $workflowId ?? $credentials->workflowId ?? config('hyperverge.url_workflow', 'onboarding');
        $redirectUrl = $redirectUrl ?? config('app.url') . '/kyc/complete';

        // Test mode bypass
        if (config('hyperverge.test_mode', false)) {
            return [
                'status' => 'success',
                'statusCode' => 200,
                'result' => [
                    'startKycUrl' => config('app.url') . '/kyc/test?transaction=' . $transactionId . '&redirect=' . urlencode($redirectUrl),
                ],
            ];
        }

        // Convert DTO to array if needed
        $optionsArray = $options instanceof LinkKycOptionsData 
            ? $options->toPayload() 
            : $options;

        $payload = array_merge([
            'workflowId' => $workflowId,
            'transactionId' => $transactionId,
            'redirectUrl' => $redirectUrl,
            'validateWorkflowInputs' => 'yes',
            'allowEmptyWorkflowInputs' => 'no',
            'forceCreateLink' => 'no',
            'redirectTime' => '5',
        ], $optionsArray);

        $headers = [
            'appId' => $credentials->appId,
            'appKey' => $credentials->appKey,
        ];

        // Log the request for debugging
        Log::debug('[GenerateOnboardingLink] Sending request', [
            'url' => ($credentials->baseUrl ?? config('hyperverge.base_url')) . $this->endpoint,
            'headers' => $credentials->toDebugArray(),
            'payload' => $payload,
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->retry(2, 200)
                ->post(($credentials->baseUrl ?? config('hyperverge.base_url')) . $this->endpoint, $payload);

            // Log full response for debugging
            Log::debug('[GenerateOnboardingLink] Received response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->failed()) {
                Log::error('[GenerateOnboardingLink] Failed to generate onboarding link', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                    'request_payload' => $payload,
                    'transaction_id' => $transactionId,
                ]);
                throw new RequestException($response);
            }

            $json = $response->json();

            Log::info('[GenerateOnboardingLink] Onboarding link created successfully', [
                'transaction_id' => $transactionId,
                'start_kyc_url' => $json['result']['startKycUrl'] ?? null,
            ]);

            return $json;
        } catch (RequestException $e) {
            throw new Exception(
                "HyperVerge onboarding link creation failed: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Shortcut method to return only the KYC link as a string.
     *
     * @param string $transactionId Unique identifier for this transaction
     * @param string|null $workflowId HyperVerge workflow ID
     * @param string|null $redirectUrl URL to redirect after completion
     * @param LinkKycOptionsData|array $options Additional API options (DTO or array)
     * @param Model|null $context Campaign, CampaignSubmission, User for credential resolution
     * @return string The onboarding link URL
     * @throws Exception
     */
    public static function get(
        string $transactionId,
        ?string $workflowId = null,
        ?string $redirectUrl = null,
        LinkKycOptionsData|array $options = [],
        ?Model $context = null
    ): string {
        $response = static::run($transactionId, $workflowId, $redirectUrl, $options, $context);
        $url = $response['result']['startKycUrl'] ?? null;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid KYC link URL received from HyperVerge.');
        }

        return $url;
    }

    /**
     * Get validation rules when used as a controller.
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:255'],
            'workflow_id' => ['nullable', 'string', 'max:255'],
            'redirect_url' => ['nullable', 'url'],
            'options' => ['nullable', 'array'],
        ];
    }

    /**
     * Map HTTP request data to action parameters.
     */
    public function mapToParameters(array $data): array
    {
        return [
            'transactionId' => $data['transaction_id'],
            'workflowId' => $data['workflow_id'] ?? null,
            'redirectUrl' => $data['redirect_url'] ?? null,
            'options' => $data['options'] ?? [],
        ];
    }

    /**
     * Handle the action as a job.
     */
    public function asJob(
        string $transactionId,
        ?string $workflowId = null,
        ?string $redirectUrl = null,
        LinkKycOptionsData|array $options = [],
        ?Model $context = null
    ): void {
        $this->handle($transactionId, $workflowId, $redirectUrl, $options, $context);
    }

    /**
     * Handle the action as a command.
     */
    public function asCommand(): int
    {
        $transactionId = $this->argument('transactionId');
        $workflowId = $this->option('workflow');
        $redirectUrl = $this->option('redirect');

        try {
            $url = static::get($transactionId, $workflowId, $redirectUrl);

            $this->info('✅ Onboarding Link Generated Successfully!');
            $this->line('');
            $this->line('Transaction ID: ' . $transactionId);
            $this->line('Onboarding URL: ' . $url);
            $this->line('');
            $this->comment('User can visit this URL to complete KYC verification.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to generate link: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Get the response data when used as a controller.
     */
    public function jsonResponse(array $result): array
    {
        return [
            'success' => $result['status'] === 'success',
            'onboarding_url' => $result['result']['startKycUrl'] ?? null,
            'transaction_id' => $result['metadata']['transactionId'] ?? null,
            'request_id' => $result['metadata']['requestId'] ?? null,
        ];
    }
}
