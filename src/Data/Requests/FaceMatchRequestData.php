<?php

namespace LBHurtado\HyperVerge\Data\Requests;

use LBHurtado\HyperVerge\Data\HypervergeRequestData;

/**
 * Request DTO for Face Match API.
 */
class FaceMatchRequestData extends HypervergeRequestData
{
    public function __construct(
        public string $referenceImage,
        public string $selfieImage,
    ) {
    }

    public static function fromBase64(string $referenceImage, string $selfieImage): self
    {
        return new self($referenceImage, $selfieImage);
    }

    public function toPayload(): array
    {
        return [
            'referenceImage' => $this->referenceImage,
            'selfieImage'    => $this->selfieImage,
        ];
    }
}
