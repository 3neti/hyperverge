<?php

namespace LBHurtado\HyperVerge\Enums;

enum SignatureMode: string
{
    /**
     * Proforma mode: Template-based signing.
     * Same document is signed multiple times, each creating a new signed copy.
     * Tile allocation resets for each new signing session.
     */
    case PROFORMA = 'proforma';

    /**
     * Roll mode: Master document accumulates signatures.
     * Single document receives multiple signatures over time.
     * Tiles accumulate and are tracked to avoid overlap.
     */
    case ROLL = 'roll';

    /**
     * Get the default signature mode.
     */
    public static function default(): self
    {
        return self::PROFORMA;
    }

    /**
     * Check if this mode is template-based (proforma).
     */
    public function isTemplate(): bool
    {
        return $this === self::PROFORMA;
    }

    /**
     * Check if this mode accumulates signatures (roll).
     */
    public function isRoll(): bool
    {
        return $this === self::ROLL;
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PROFORMA => 'Proforma (Template)',
            self::ROLL => 'Roll (Accumulate)',
        };
    }

    /**
     * Get description of the mode.
     */
    public function description(): string
    {
        return match ($this) {
            self::PROFORMA => 'Each signer gets a fresh copy. Ideal for forms, contracts, waivers.',
            self::ROLL => 'All signatures accumulate on one master document. Ideal for petitions, attendance sheets.',
        };
    }
}
