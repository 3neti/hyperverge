<?php

namespace LBHurtado\HyperVerge\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

/**
 * Signature validator for HyperVerge webhooks.
 * 
 * Validates incoming webhook requests using HMAC SHA256 signature.
 * HyperVerge sends signature in X-HyperVerge-Signature header.
 */
class HypervergeSignatureValidator implements SignatureValidator
{
    /**
     * Validate the webhook signature.
     *
     * @param Request $request
     * @param WebhookConfig $config
     * @return bool
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header($config->signatureHeaderName);

        if (!$signature) {
            return false;
        }

        $signingSecret = $config->signingSecret;

        if (empty($signingSecret)) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $request->getContent(), $signingSecret);

        return hash_equals($computedSignature, $signature);
    }
}
