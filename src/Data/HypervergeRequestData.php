<?php

namespace LBHurtado\HyperVerge\Data;

use Spatie\LaravelData\Data;

/**
 * Base class for all HyperVerge request DTOs.
 * Every request knows how to transform itself into an API payload.
 */
abstract class HypervergeRequestData extends Data
{
    /**
     * Convert this DTO into the payload expected by HyperVerge's API.
     */
    abstract public function toPayload(): array;
}
