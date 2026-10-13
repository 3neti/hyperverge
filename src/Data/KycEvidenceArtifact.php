<?php

declare(strict_types=1);

namespace LBHurtado\HyperVerge\Data;

use Illuminate\Contracts\Support\Arrayable;

final readonly class KycEvidenceArtifact implements Arrayable
{
    public function __construct(
        public string $type,
        public ?string $variant,
        public string $mediaId,
        public string $transactionId,
        public string $mimeType,
        public int $size,
        public string $sha256,
        public string $collection,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'variant' => $this->variant,
            'media_id' => $this->mediaId,
            'transaction_id' => $this->transactionId,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'collection' => $this->collection,
        ];
    }
}
