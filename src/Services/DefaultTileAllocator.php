<?php

namespace LBHurtado\HyperVerge\Services;

use LBHurtado\HyperVerge\Contracts\TileAllocator;

class DefaultTileAllocator implements TileAllocator
{
    /**
     * Get the next available tile position.
     */
    public function nextTile(array $usedTiles = [], int $maxTiles = 9): ?int
    {
        for ($tile = 1; $tile <= $maxTiles; $tile++) {
            if (!in_array($tile, $usedTiles)) {
                return $tile;
            }
        }

        return null; // All tiles are used
    }

    /**
     * Get position coordinates for a tile number.
     */
    public function getTilePosition(int $tile): array
    {
        $positions = config('hyperverge.document_signing.watermark.tile_positions', $this->getDefaultPositions());

        return $positions[$tile] ?? $positions[1]; // Fallback to position 1 (bottom-right)
    }

    /**
     * Reset tile allocation.
     */
    public function reset(): array
    {
        return [];
    }

    /**
     * Get default 3x3 grid positions.
     */
    protected function getDefaultPositions(): array
    {
        return [
            1 => ['vertical' => 'bottom', 'horizontal' => 'right', 'offsetX' => 10, 'offsetY' => 10],
            2 => ['vertical' => 'bottom', 'horizontal' => 'center', 'offsetX' => 0, 'offsetY' => 10],
            3 => ['vertical' => 'bottom', 'horizontal' => 'left', 'offsetX' => 10, 'offsetY' => 10],
            4 => ['vertical' => 'center', 'horizontal' => 'right', 'offsetX' => 10, 'offsetY' => 0],
            5 => ['vertical' => 'center', 'horizontal' => 'center', 'offsetX' => 0, 'offsetY' => 0],
            6 => ['vertical' => 'center', 'horizontal' => 'left', 'offsetX' => 10, 'offsetY' => 0],
            7 => ['vertical' => 'top', 'horizontal' => 'right', 'offsetX' => 10, 'offsetY' => 10],
            8 => ['vertical' => 'top', 'horizontal' => 'center', 'offsetX' => 0, 'offsetY' => 10],
            9 => ['vertical' => 'top', 'horizontal' => 'left', 'offsetX' => 10, 'offsetY' => 10],
        ];
    }
}
