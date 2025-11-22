<?php

namespace LBHurtado\HyperVerge\Traits;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasDocuments
{
    /**
     * Add original document.
     */
    public function addDocument(string $path, array $customProperties = []): Media
    {
        return $this->addMedia($path)
            ->withCustomProperties($customProperties)
            ->toMediaCollection('documents');
    }

    /**
     * Get original document.
     */
    public function getDocumentAttribute(): ?Media
    {
        return $this->getFirstMedia('documents');
    }

    /**
     * Add signed/watermarked document.
     */
    public function addSignedDocument(string $path, array $metadata = []): Media
    {
        return $this->addMedia($path)
            ->withCustomProperties(array_merge($metadata, [
                'signed_at' => now()->toIso8601String(),
            ]))
            ->toMediaCollection('signed_documents');
    }

    /**
     * Get signed document.
     */
    public function getSignedDocumentAttribute(): ?Media
    {
        return $this->getFirstMedia('signed_documents');
    }

    /**
     * Get all signed documents (for multiple signatures).
     */
    public function getSignedDocumentsAttribute()
    {
        return $this->getMedia('signed_documents');
    }

    /**
     * Add signature mark/stamp image.
     */
    public function addSignatureMark(string $path, array $metadata = []): Media
    {
        return $this->addMedia($path)
            ->withCustomProperties($metadata)
            ->toMediaCollection('signature_marks');
    }

    /**
     * Get signature mark.
     */
    public function getSignatureMarkAttribute(): ?Media
    {
        return $this->getFirstMedia('signature_marks');
    }

    /**
     * Get all signature marks (for multiple signatures).
     */
    public function getSignatureMarksAttribute()
    {
        return $this->getMedia('signature_marks');
    }

    /**
     * Add tracked document (with QR code).
     */
    public function addTrackedDocument(string $path, array $metadata = []): Media
    {
        return $this->addMedia($path)
            ->withCustomProperties(array_merge($metadata, [
                'tracked_on' => now()->toIso8601String(),
            ]))
            ->toMediaCollection('tracked_documents');
    }

    /**
     * Get tracked document.
     */
    public function getTrackedDocumentAttribute(): ?Media
    {
        return $this->getFirstMedia('tracked_documents');
    }

    /**
     * Check if model has a document.
     */
    public function hasDocument(): bool
    {
        return $this->hasMedia('documents');
    }

    /**
     * Check if model has signed documents.
     */
    public function hasSignedDocuments(): bool
    {
        return $this->hasMedia('signed_documents');
    }
}
