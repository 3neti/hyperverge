<?php

namespace LBHurtado\HyperVerge\Data\Requests;

use LBHurtado\HyperVerge\Data\HypervergeRequestData;

/**
 * Request DTO for Selfie Liveness API.
 */
class SelfieLivenessRequestData extends HypervergeRequestData
{
    public function __construct(
        public string $imageBase64,
    ) {
    }

    public static function fromBase64(string $imageBase64): self
    {
        return new self($imageBase64);
    }

    public function toPayload(): array
    {
        return [
            'image' => $this->imageBase64,
        ];
    }
}
