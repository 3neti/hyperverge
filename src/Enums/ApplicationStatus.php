<?php

namespace LBHurtado\HyperVerge\Enums;

/**
 * HyperVerge application status values.
 * 
 * These are the possible values returned by HyperVerge
 * for the applicationStatus field in KYC results.
 */
enum ApplicationStatus: string
{
    case AUTO_APPROVED = 'auto_approved';
    case APPROVED = 'approved';
    case NEEDS_REVIEW = 'needs_review';
    case AUTO_DECLINED = 'auto_declined';
    case REJECTED = 'rejected';
    case USER_CANCELLED = 'user_cancelled';
    case ERROR = 'error';

    /**
     * Check if this status indicates approval/success.
     */
    public function isApproved(): bool
    {
        return in_array($this, [
            self::AUTO_APPROVED,
            self::APPROVED,
            self::NEEDS_REVIEW,
        ]);
    }

    /**
     * Check if this status indicates rejection/failure.
     */
    public function isRejected(): bool
    {
        return in_array($this, [
            self::AUTO_DECLINED,
            self::REJECTED,
            self::USER_CANCELLED,
            self::ERROR,
        ]);
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::AUTO_APPROVED => 'Auto Approved',
            self::APPROVED => 'Approved',
            self::NEEDS_REVIEW => 'Needs Review',
            self::AUTO_DECLINED => 'Auto Declined',
            self::REJECTED => 'Rejected',
            self::USER_CANCELLED => 'User Cancelled',
            self::ERROR => 'Error',
        };
    }

    /**
     * Get all approved statuses.
     */
    public static function approved(): array
    {
        return [
            self::AUTO_APPROVED->value,
            self::APPROVED->value,
            self::NEEDS_REVIEW->value,
        ];
    }

    /**
     * Get all rejected statuses.
     */
    public static function rejected(): array
    {
        return [
            self::AUTO_DECLINED->value,
            self::REJECTED->value,
            self::USER_CANCELLED->value,
            self::ERROR->value,
        ];
    }
}
