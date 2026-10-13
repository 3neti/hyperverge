<?php

declare(strict_types=1);

namespace LBHurtado\HyperVerge\Services;

use Illuminate\Support\Facades\Storage;
use LBHurtado\HyperVerge\Data\KycEvidenceArtifact;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use RuntimeException;

final class KycEvidenceCatalog
{
    /** @return list<KycEvidenceArtifact> */
    public function for(HasMedia $model, ?string $transactionId = null): array
    {
        $items = [];

        foreach (['kyc_selfies', 'kyc_id_cards'] as $collection) {
            foreach ($model->getMedia($collection) as $media) {
                $properties = $media->custom_properties ?? [];
                $storedTransactionId = (string) ($properties['transaction_id'] ?? '');

                if ($transactionId !== null && $storedTransactionId !== $transactionId) {
                    continue;
                }

                $key = (string) ($properties['kyc_image_type'] ?? $media->name);
                [$type, $variant] = $this->typeAndVariant($key, $collection);

                $items[] = new KycEvidenceArtifact(
                    type: $type,
                    variant: $variant,
                    mediaId: (string) $media->getKey(),
                    transactionId: $storedTransactionId,
                    mimeType: (string) ($properties['mime_type'] ?? $media->mime_type),
                    size: (int) ($properties['file_size'] ?? $media->size),
                    sha256: $this->checksum($media),
                    collection: $collection,
                );
            }
        }

        return $items;
    }

    /** @return array{contents:string,mime_type:string,extension:string,size:int,sha256:string} */
    public function read(HasMedia $model, string $mediaId): array
    {
        $media = collect(['kyc_selfies', 'kyc_id_cards'])
            ->flatMap(fn (string $collection) => $model->getMedia($collection))
            ->first(fn (Media $media): bool => (string) $media->getKey() === $mediaId);

        if (! $media instanceof Media) {
            throw new RuntimeException('KYC evidence artifact was not found.');
        }

        $disk = Storage::disk($media->disk);
        $path = method_exists($media, 'getPathRelativeToRoot')
            ? $media->getPathRelativeToRoot()
            : $media->getPath();
        $contents = $disk->get($path);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('KYC evidence artifact has an unsupported MIME type.');
        }

        $sha256 = hash('sha256', $contents);
        $expectedSha256 = (string) (($media->custom_properties ?? [])['sha256'] ?? '');

        if ($expectedSha256 !== '' && ! hash_equals($expectedSha256, $sha256)) {
            throw new RuntimeException('KYC evidence artifact checksum did not match.');
        }

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'extension' => match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            },
            'size' => strlen($contents),
            'sha256' => $sha256,
        ];
    }

    private function checksum(Media $media): string
    {
        $properties = $media->custom_properties ?? [];

        return (string) ($properties['sha256'] ?? '');
    }

    /** @return array{string, ?string} */
    private function typeAndVariant(string $key, string $collection): array
    {
        if ($collection === 'kyc_selfies' || str_contains($key, 'selfie')) {
            return ['selfie', null];
        }

        return ['government_id', str_contains($key, 'cropped') ? 'cropped' : 'full'];
    }
}
