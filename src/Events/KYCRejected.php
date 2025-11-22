<?php

namespace LBHurtado\HyperVerge\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\HyperVerge\Data\Validation\KYCValidationResultData;

/**
 * Event fired when KYC verification is rejected.
 * 
 * This event is dispatched after a KYC verification fails validation rules.
 * Listen to this event to trigger post-rejection workflows like:
 * - Notifying user of rejection
 * - Logging rejection reasons
 * - Requesting re-verification
 * - Flagging account for review
 * 
 * Usage:
 * 
 * // Listen in EventServiceProvider
 * protected $listen = [
 *     KYCRejected::class => [
 *         SendRejectionNotification::class,
 *         LogKYCRejection::class,
 *     ],
 * ];
 * 
 * // Or listen inline
 * Event::listen(function (KYCRejected $event) {
 *     $user = User::where('kyc_transaction_id', $event->result->transactionId)->first();
 *     $user->update([
 *         'kyc_rejected_at' => now(),
 *         'kyc_rejection_reasons' => $event->validation->reasons,
 *     ]);
 * });
 */
class KYCRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param KYCResultData $result The complete KYC result data
     * @param KYCValidationResultData $validation The validation result with rejection reasons
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
