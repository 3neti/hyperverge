<?php

namespace LBHurtado\HyperVerge\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

/**
 * Webhook profile for HyperVerge webhooks.
 * 
 * Determines which incoming webhook requests should be processed.
 * Performs basic validation before job dispatch.
 */
class HypervergeWebhookProfile implements WebhookProfile
{
    /**
     * Determine if the webhook request should be processed.
     *
     * @param Request $request
     * @return bool
     */
    public function shouldProcess(Request $request): bool
    {
        // Only process POST requests
        if (!$request->isMethod('post')) {
            return false;
        }

        // Verify webhook has required HyperVerge data
        $payload = $request->all();
        
        // HyperVerge webhooks should have transactionId
        if (!isset($payload['transactionId']) && !isset($payload['metadata']['transactionId'])) {
            return false;
        }

        // HyperVerge webhooks should have status information
        if (!isset($payload['status']) && !isset($payload['applicationStatus'])) {
            return false;
        }

        return true;
    }
}
