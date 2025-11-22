<?php

namespace LBHurtado\HyperVerge\Actions\LinkKYC;

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Support\HypervergeClient;
use LBHurtado\HyperVerge\Data\Requests\LinkKycRequestData;

/**
 * Create a Link KYC session and return the verification URL.
 * 
 * This action creates a new KYC verification session with HyperVerge
 * and returns the URL where users can complete their verification.
 * 
 * @example
 * $result = CreateKYCSession::run(
 *     transactionId: 'user_123_' . time(),
 *     workflowId: 'onboarding',
 *     redirectUrl: route('kyc.callback')
 * );
 * 
 * @see https://documentation.hyperverge.co
 */
class CreateKYCSession
{
    use AsAction;

    /**
     * The command signature for artisan usage.
     */
    public string $commandSignature = 'hyperverge:create-session 
                                        {transactionId : The transaction ID}
                                        {workflowId : The workflow ID}
                                        {redirectUrl : The redirect URL after completion}';

    /**
     * The command description.
     */
    public string $commandDescription = 'Create a new HyperVerge KYC session';

    /**
     * Execute the action to create a KYC session.
     *
     * @param string $transactionId Unique identifier for this transaction
     * @param string $workflowId HyperVerge workflow ID (e.g., 'onboarding')
     * @param string $redirectUrl URL to redirect user after KYC completion
     * @return array Response containing startKycUrl and other details
     */
    public function handle(
        string $transactionId,
        string $workflowId,
        string $redirectUrl
    ): array {
        $client = app(HypervergeClient::class);
        
        $request = LinkKycRequestData::make(
            $transactionId,
            $workflowId,
            $redirectUrl
        );
        
        return $client->post('/link-kyc/start', $request->toPayload());
    }

    /**
     * Get validation rules when used as a controller.
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:255'],
            'workflow_id' => ['required', 'string', 'max:255'],
            'redirect_url' => ['required', 'url'],
        ];
    }

    /**
     * Map HTTP request data to action parameters when used as controller.
     */
    public function mapToParameters(array $data): array
    {
        return [
            'transactionId' => $data['transaction_id'],
            'workflowId' => $data['workflow_id'],
            'redirectUrl' => $data['redirect_url'],
        ];
    }

    /**
     * Handle the action as a job.
     */
    public function asJob(
        string $transactionId,
        string $workflowId,
        string $redirectUrl
    ): void {
        $this->handle($transactionId, $workflowId, $redirectUrl);
    }

    /**
     * Handle the action as a command.
     */
    public function asCommand(): int
    {
        $result = $this->handle(
            $this->argument('transactionId'),
            $this->argument('workflowId'),
            $this->argument('redirectUrl')
        );

        $this->info('✅ KYC Session Created Successfully!');
        $this->line('');
        $this->line('Transaction ID: ' . ($result['metadata']['transactionId'] ?? 'N/A'));
        $this->line('KYC URL: ' . ($result['result']['startKycUrl'] ?? 'N/A'));

        return self::SUCCESS;
    }

    /**
     * Get the response data when used as a controller.
     */
    public function jsonResponse(array $result): array
    {
        return [
            'success' => $result['status'] === 'success',
            'transaction_id' => $result['metadata']['transactionId'] ?? null,
            'kyc_url' => $result['result']['startKycUrl'] ?? null,
            'request_id' => $result['metadata']['requestId'] ?? null,
        ];
    }
}
