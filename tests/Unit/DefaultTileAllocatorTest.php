<?php

use LBHurtado\HyperVerge\Services\DefaultTileAllocator;

beforeEach(function () {
    $this->allocator = new DefaultTileAllocator();
});

test('it returns tile 1 when no tiles are used', function () {
    $tile = $this->allocator->nextTile();
    
    expect($tile)->toBe(1);
});

test('it returns next available tile when some are used', function () {
    $usedTiles = [1, 2, 3];
    $tile = $this->allocator->nextTile($usedTiles);
    
    expect($tile)->toBe(4);
});

test('it returns null when all tiles are used', function () {
    $usedTiles = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    $tile = $this->allocator->nextTile($usedTiles);
    
    expect($tile)->toBeNull();
});

test('it handles non-sequential used tiles', function () {
    $usedTiles = [1, 3, 5, 7];
    $tile = $this->allocator->nextTile($usedTiles);
    
    expect($tile)->toBe(2); // First gap
});

test('it respects custom max tiles', function () {
    $usedTiles = [1, 2, 3];
    $tile = $this->allocator->nextTile($usedTiles, maxTiles: 4);
    
    expect($tile)->toBe(4);
    
    $usedTiles = [1, 2, 3, 4];
    $tile = $this->allocator->nextTile($usedTiles, maxTiles: 4);
    
    expect($tile)->toBeNull();
});

test('it returns correct position for each tile', function () {
    $positions = [
        1 => ['bottom', 'right'],
        2 => ['bottom', 'center'],
        3 => ['bottom', 'left'],
        4 => ['center', 'right'],
        5 => ['center', 'center'],
        6 => ['center', 'left'],
        7 => ['top', 'right'],
        8 => ['top', 'center'],
        9 => ['top', 'left'],
    ];
    
    foreach ($positions as $tile => [$vertical, $horizontal]) {
        $position = $this->allocator->getTilePosition($tile);
        
        expect($position)
            ->toHaveKey('vertical')
            ->toHaveKey('horizontal')
            ->and($position['vertical'])->toBe($vertical)
            ->and($position['horizontal'])->toBe($horizontal);
    }
});

test('it returns fallback position for invalid tile number', function () {
    $position = $this->allocator->getTilePosition(999);
    
    expect($position)
        ->toHaveKey('vertical')
        ->toHaveKey('horizontal')
        ->and($position['vertical'])->toBe('bottom')
        ->and($position['horizontal'])->toBe('right');
});

test('it resets tile allocation', function () {
    $reset = $this->allocator->reset();
    
    expect($reset)->toBe([]);
});
