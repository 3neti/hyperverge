<?php

namespace LBHurtado\HyperVerge\Data\Responses;

use LBHurtado\HyperVerge\Data\HypervergeResponseData;

/**
 * Response DTO for Face Match API.
 */
class FaceMatchResponseData extends HypervergeResponseData
{
    public function __construct(
        public bool $isMatch,
        public float $confidence,
        public array $quality,
        array $raw,
        array $meta = [],
    ) {
        parent::__construct(raw: $raw, meta: $meta);
    }

    public static function fromHyperverge(array $response, array $meta = []): self
    {
        return new self(
            isMatch: (bool)($response['result']['isMatch'] ?? false),
            confidence: (float)($response['result']['score'] ?? 0.0),
            quality: $response['result']['quality'] ?? [],
            raw: $response,
            meta: $meta,
        );
    }
}
