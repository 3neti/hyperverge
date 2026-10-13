<?php

declare(strict_types=1);

use LBHurtado\HyperVerge\Data\KycEvidenceArtifact;

it('serializes normalized KYC evidence metadata without exposing storage paths', function (): void {
    $artifact = new KycEvidenceArtifact(
        type: 'government_id',
        variant: 'full',
        mediaId: '42',
        transactionId: 'hv-123',
        mimeType: 'image/jpeg',
        size: 1024,
        sha256: str_repeat('a', 64),
        collection: 'kyc_id_cards',
    );

    expect($artifact->toArray())
        ->toMatchArray([
            'type' => 'government_id',
            'variant' => 'full',
            'media_id' => '42',
            'transaction_id' => 'hv-123',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'sha256' => str_repeat('a', 64),
            'collection' => 'kyc_id_cards',
        ])
        ->not->toHaveKey('path');
});
