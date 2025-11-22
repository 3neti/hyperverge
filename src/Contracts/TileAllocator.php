<?php

namespace LBHurtado\HyperVerge\Contracts;

interface TileAllocator
{
    /**
     * Get the next available tile position for a signature stamp.
     *
     * @param array $usedTiles Array of already-used tile numbers
     * @param int $maxTiles Maximum number of tiles (default 9 for 3x3 grid)
     * @return int|null The next available tile number (1-based), or null if all tiles are used
     */
    public function nextTile(array $usedTiles = [], int $maxTiles = 9): ?int;

    /**
     * Get the position coordinates for a given tile number.
     *
     * @param int $tile Tile number (1-based)
     * @return array ['vertical' => 'top'|'center'|'bottom', 'horizontal' => 'left'|'center'|'right', 'offsetX' => int, 'offsetY' => int]
     */
    public function getTilePosition(int $tile): array;

    /**
     * Reset tile allocation (e.g., for template/proforma mode).
     *
     * @return array Empty array representing fresh tile state
     */
    public function reset(): array;
}
