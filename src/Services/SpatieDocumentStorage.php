<?php

namespace LBHurtado\HyperVerge\Services;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\HyperVerge\Contracts\DocumentStoragePort;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SpatieDocumentStorage implements DocumentStoragePort
{
    /**
     * Store a document file.
     */
    public function storeDocument(Model $model, string $filePath, string $collection, array $customProperties = []): mixed
    {
        if (!method_exists($model, 'addMedia')) {
            throw new \RuntimeException(
                'Model must use Spatie\MediaLibrary\HasMedia trait. ' .
                'Model: ' . get_class($model)
            );
        }

        return $model->addMedia($filePath)
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);
    }

    /**
     * Retrieve a document from a collection.
     */
    public function getDocument(Model $model, string $collection): mixed
    {
        if (!method_exists($model, 'getFirstMedia')) {
            return null;
        }

        return $model->getFirstMedia($collection);
    }

    /**
     * Get absolute file path for stored document.
     */
    public function getPath(mixed $media): string
    {
        if (!$media instanceof Media) {
            throw new \InvalidArgumentException('Media must be instance of Spatie Media');
        }

        return $media->getPath();
    }

    /**
     * Get public URL for stored document.
     */
    public function getUrl(mixed $media): string
    {
        if (!$media instanceof Media) {
            throw new \InvalidArgumentException('Media must be instance of Spatie Media');
        }

        return $media->getUrl();
    }

    /**
     * Check if document exists in collection.
     */
    public function hasDocument(Model $model, string $collection): bool
    {
        if (!method_exists($model, 'hasMedia')) {
            return false;
        }

        return $model->hasMedia($collection);
    }

    /**
     * Delete document from collection.
     */
    public function deleteDocument(Model $model, string $collection): bool
    {
        if (!method_exists($model, 'getFirstMedia')) {
            return false;
        }

        $media = $model->getFirstMedia($collection);
        
        if ($media) {
            $media->delete();
            return true;
        }

        return false;
    }
}
