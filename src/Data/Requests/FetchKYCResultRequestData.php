<?php

namespace LBHurtado\HyperVerge\Data\Requests;

use LBHurtado\HyperVerge\Data\HypervergeRequestData;

/**
 * Request DTO for fetching KYC Result.
 * Based on HyperVerge Link KYC API: POST /link-kyc/results
 */
class FetchKYCResultRequestData extends HypervergeRequestData
{
    public function __construct(
        public string $transactionId,
    ) {
    }

    public static function fromTransaction(string $transactionId): self
    {
        return new self($transactionId);
    }

    public function toPayload(): array
    {
        return [
            'transactionId' => $this->transactionId,
        ];
    }
}
