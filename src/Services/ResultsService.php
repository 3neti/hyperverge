<?php

namespace LBHurtado\HyperVerge\Services;

use LBHurtado\HyperVerge\Data\Requests\FetchKYCResultRequestData;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\HyperVerge\Support\HypervergeClient;

class ResultsService
{
    public function __construct(
        protected HypervergeClient $client
    ) {
    }

    public function fetch(string $transactionId): KYCResultData
    {
        $request = FetchKYCResultRequestData::fromTransaction($transactionId);
        
        $response = $this->client->post('/link-kyc/results', $request->toPayload());

        return KYCResultData::fromHyperverge($response);
    }

    public function fetchFromRequest(FetchKYCResultRequestData $request): KYCResultData
    {
        $response = $this->client->post('/link-kyc/results', $request->toPayload());
        
        return KYCResultData::fromHyperverge($response);
    }
}
