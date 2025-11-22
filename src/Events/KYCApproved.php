<?php

namespace LBHurtado\HyperVerge\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\HyperVerge\Data\Validation\KYCValidationResultData;

/**
 * Event fired when KYC verification is approved.
 * 
 * This event is dispatched after a KYC verification passes all validation rules.
 * Listen to this event to trigger post-approval workflows like:
 * - Updating user status
 * - Sending approval notifications
 * - Enabling account features
 * - Logging approval for audit
 * 
 * Usage:
 * 
 * // Listen in EventServiceProvider
 * protected $listen = [
 *     KYCApproved::class => [
 *         SendApprovalNotification::class,
 *         UpdateUserKYCStatus::class,
 *     ],
 * ];
 * 
 * // Or listen inline
 * Event::listen(function (KYCApproved $event) {
 *     $user = User::where('kyc_transaction_id', $event->result->transactionId)->first();
 *     $user->update(['kyc_approved_at' => now()]);
 * });
 */
class KYCApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param KYCResultData $result The complete KYC result data
     * @param KYCValidationResultData $validation The validation result
     * @param string $transactionId The transaction ID for convenience
     */
    public function __construct(
        public KYCResultData $result,
        public KYCValidationResultData $validation,
        public string $transactionId,
    ) {
    }

    /**
     * Get the channels the event should broadcast on (if needed).
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
