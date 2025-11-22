<?php

namespace LBHurtado\HyperVerge\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when KYC data and images are successfully saved.
 * 
 * This event is dispatched by SaveKYCDataWithTransaction after
 * both images and data have been stored in a single atomic transaction.
 */
class KYCDataSaved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model that received KYC data
     * @param string $transactionId The HyperVerge transaction ID
     * @param array $result Complete result from SaveKYCDataWithTransaction
     */
    public function __construct(
        public Model $model,
        public string $transactionId,
        public array $result
    ) {
    }
}
