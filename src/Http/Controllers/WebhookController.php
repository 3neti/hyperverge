<?php

namespace LBHurtado\HyperVerge\Http\Controllers;

use LBHurtado\HyperVerge\Data\WebhookData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function __invoke(Request $request)
    {
        $webhookData = WebhookData::fromRequest($request->all());

        // Log the webhook for debugging
        Log::info('HyperVerge Webhook Received', [
            'session_id' => $webhookData->sessionId,
            'status' => $webhookData->status,
            'payload' => $webhookData->payload,
        ]);

        // Fire an event that can be listened to by the application
        event('hyperverge.webhook.received', $webhookData);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
        ]);
    }
}
