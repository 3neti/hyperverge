<?php

namespace LBHurtado\HyperVerge\Data\Responses;

use LBHurtado\HyperVerge\Data\HypervergeResponseData;

/**
 * Response DTO for Selfie Liveness API.
 */
class SelfieLivenessResponseData extends HypervergeResponseData
{
    public function __construct(
        public bool $isLive,
        public array $quality,
        array $raw,
        array $meta = [],
    ) {
        parent::__construct(raw: $raw, meta: $meta);
    }

    public static function fromHyperverge(array $response, array $meta = []): self
    {
        return new self(
            isLive: (bool)($response['result']['isLive'] ?? false),
            quality: $response['result']['quality'] ?? [],
            raw: $response,
            meta: $meta,
        );
    }
}
