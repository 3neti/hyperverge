<?php

namespace LBHurtado\HyperVerge\Services;

use LBHurtado\HyperVerge\Data\Requests\FaceMatchRequestData;
use LBHurtado\HyperVerge\Data\Responses\FaceMatchResponseData;
use LBHurtado\HyperVerge\Exceptions\FaceMatchFailedException;
use LBHurtado\HyperVerge\Support\HypervergeClient;

class FaceMatchService
{
    public function __construct(
        protected HypervergeClient $client
    ) {
    }

    public function match(string $referenceImage, string $selfieImage): FaceMatchResponseData
    {
        $request = FaceMatchRequestData::fromBase64($referenceImage, $selfieImage);
        
        $response = $this->client->post('/v1/face/match', $request->toPayload());

        $result = FaceMatchResponseData::fromHyperverge($response);

        if (! $result->isMatch) {
            throw new FaceMatchFailedException('Face match failed.', $response);
        }

        return $result;
    }

    public function matchFromRequest(FaceMatchRequestData $request): FaceMatchResponseData
    {
        $response = $this->client->post('/v1/face/match', $request->toPayload());
        
        return FaceMatchResponseData::fromHyperverge($response);
    }
}
