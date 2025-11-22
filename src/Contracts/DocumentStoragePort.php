<?php

namespace LBHurtado\HyperVerge\Contracts;

use Illuminate\Database\Eloquent\Model;

interface DocumentStoragePort
{
    /**
     * Store a document file.
     *
     * @param Model $model The model that owns the document
     * @param string $filePath Absolute path to the file
     * @param string $collection Collection name (e.g., 'documents', 'signed_documents')
     * @param array $customProperties Optional custom properties/metadata
     * @return mixed The stored media object (implementation-specific)
     */
    public function storeDocument(Model $model, string $filePath, string $collection, array $customProperties = []): mixed;

    /**
     * Retrieve a document from a specific collection.
     *
     * @param Model $model The model that owns the document
     * @param string $collection Collection name
     * @return mixed|null The media object or null if not found
     */
    public function getDocument(Model $model, string $collection): mixed;

    /**
     * Get the absolute file path for a stored document.
     *
     * @param mixed $media The media object
     * @return string Absolute path to the file
     */
    public function getPath(mixed $media): string;

    /**
     * Get the public URL for a stored document.
     *
     * @param mixed $media The media object
     * @return string Public URL
     */
    public function getUrl(mixed $media): string;

    /**
     * Check if a document exists in a collection.
     *
     * @param Model $model The model that owns the document
     * @param string $collection Collection name
     * @return bool
     */
    public function hasDocument(Model $model, string $collection): bool;

    /**
     * Delete a document from a collection.
     *
     * @param Model $model The model that owns the document
     * @param string $collection Collection name
     * @return bool Success status
     */
    public function deleteDocument(Model $model, string $collection): bool;
}
