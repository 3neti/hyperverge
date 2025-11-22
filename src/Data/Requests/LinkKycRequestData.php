<?php

namespace LBHurtado\HyperVerge\Data\Requests;

use LBHurtado\HyperVerge\Data\HypervergeRequestData;

/**
 * Request DTO for Link KYC session creation.
 * Based on HyperVerge Link KYC API: POST /link-kyc/start
 * Note: metadata field is not supported by the API
 */
class LinkKycRequestData extends HypervergeRequestData
{
    public function __construct(
        public string $transactionId,
        public string $workflowId,
        public string $redirectUrl,
    ) {
    }

    public static function make(string $transactionId, string $workflowId, string $redirectUrl): self
    {
        return new self($transactionId, $workflowId, $redirectUrl);
    }

    public function toPayload(): array
    {
        return [
            'transactionId' => $this->transactionId,
            'workflowId'    => $this->workflowId,
            'redirectUrl'   => $this->redirectUrl,
        ];
    }
}
