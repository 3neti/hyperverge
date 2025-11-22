<?php

use Illuminate\Database\Eloquent\Model;
use LBHurtado\HyperVerge\Services\SpatieDocumentStorage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// Test model that uses Spatie Media Library
class TestDocument extends Model implements HasMedia
{
    use InteractsWithMedia;
    
    protected $table = 'test_documents';
}

// Test model without Spatie trait
class InvalidDocument extends Model
{
    protected $table = 'invalid_documents';
}

beforeEach(function () {
    $this->storage = new SpatieDocumentStorage();
});

test('it throws exception when model does not use HasMedia trait', function () {
    $model = new InvalidDocument();
    $filePath = __DIR__ . '/../Fixtures/test.pdf';
    
    expect(fn () => $this->storage->storeDocument($model, $filePath, 'documents'))
        ->toThrow(RuntimeException::class, 'Model must use Spatie');
});

test('it returns null when getting document from model without trait', function () {
    $model = new InvalidDocument();
    
    $document = $this->storage->getDocument($model, 'documents');
    
    expect($document)->toBeNull();
});

test('it returns false when checking document existence on model without trait', function () {
    $model = new InvalidDocument();
    
    $has = $this->storage->hasDocument($model, 'documents');
    
    expect($has)->toBeFalse();
});

test('it returns false when deleting document from model without trait', function () {
    $model = new InvalidDocument();
    
    $deleted = $this->storage->deleteDocument($model, 'documents');
    
    expect($deleted)->toBeFalse();
});

test('it throws exception when getting path from non-media object', function () {
    expect(fn () => $this->storage->getPath('not-a-media'))
        ->toThrow(InvalidArgumentException::class, 'Media must be instance of Spatie Media');
});

test('it throws exception when getting url from non-media object', function () {
    expect(fn () => $this->storage->getUrl('not-a-media'))
        ->toThrow(InvalidArgumentException::class, 'Media must be instance of Spatie Media');
});
