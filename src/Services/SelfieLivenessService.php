<?php

namespace LBHurtado\HyperVerge\Services;

use LBHurtado\HyperVerge\Data\Requests\SelfieLivenessRequestData;
use LBHurtado\HyperVerge\Data\Responses\SelfieLivenessResponseData;
use LBHurtado\HyperVerge\Exceptions\LivelinessFailedException;
use LBHurtado\HyperVerge\Support\HypervergeClient;

class SelfieLivenessService
{
    public function __construct(
        protected HypervergeClient $client
    ) {
    }

    public function verify(string $base64Image): SelfieLivenessResponseData
    {
        $request = SelfieLivenessRequestData::fromBase64($base64Image);
        
        $response = $this->client->post('/v1/selfie/liveness', $request->toPayload());

        $result = SelfieLivenessResponseData::fromHyperverge($response);

        if (! $result->isLive) {
            throw new LivelinessFailedException('Liveness check failed.', $response);
        }

        return $result;
    }

    public function verifyFromRequest(SelfieLivenessRequestData $request): SelfieLivenessResponseData
    {
        $response = $this->client->post('/v1/selfie/liveness', $request->toPayload());
        
        return SelfieLivenessResponseData::fromHyperverge($response);
    }
}
