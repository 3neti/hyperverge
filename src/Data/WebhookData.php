<?php

namespace LBHurtado\HyperVerge\Data;

use Spatie\LaravelData\Data;

/**
 * DTO for HyperVerge webhook payloads.
 */
class WebhookData extends Data
{
    public function __construct(
        public string $sessionId,
        public string $status,
        public array $payload,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            sessionId: $data['sessionId'] ?? $data['session_id'] ?? '',
            status: $data['status'] ?? '',
            payload: $data,
        );
    }
}
