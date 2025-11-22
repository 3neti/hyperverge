<?php

namespace LBHurtado\HyperVerge\Services;

use LBHurtado\HyperVerge\Data\Requests\LinkKycRequestData;
use LBHurtado\HyperVerge\Support\HypervergeClient;

class LinkKYCService
{
    public function __construct(
        protected HypervergeClient $client
    ) {
    }

    public function createSession(string $transactionId, string $workflowId, string $redirectUrl): array
    {
        $request = LinkKycRequestData::make($transactionId, $workflowId, $redirectUrl);
        
        return $this->client->post('/link-kyc/start', $request->toPayload());
    }

    public function createSessionFromRequest(LinkKycRequestData $request): array
    {
        return $this->client->post('/link-kyc/start', $request->toPayload());
    }
}
