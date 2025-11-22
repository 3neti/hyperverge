<?php

namespace LBHurtado\HyperVerge\Data;

use Spatie\LaravelData\Data;

/**
 * Base class for all HyperVerge response DTOs.
 * Wraps the raw API response and optional metadata.
 */
abstract class HypervergeResponseData extends Data
{
    public function __construct(
        public array $raw,
        public array $meta = [],
    ) {
    }
}
