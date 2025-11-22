<?php

namespace LBHurtado\HyperVerge\Actions\Timestamp;

use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBlockchainTimestamp
{
    use AsAction;

    /**
     * Create blockchain timestamp for document using OpenTimestamps.
     *
     * @param  string  $documentPath  - Path to document
     * @return array - Timestamp info with proof
     */
    public function handle(string $documentPath): array
    {
        // 1. Calculate document hash
        $hash = $this->calculateDocumentHash($documentPath);

        // 2. Submit to OpenTimestamps
        $timestampProof = $this->submitToOpenTimestamps($hash);

        // 3. Store proof for later verification
        return [
            'hash' => $hash,
            'timestamp_date' => now()->toIso8601String(),
            'proof' => $timestampProof,
            'blockchain' => 'Bitcoin',
            'service' => 'OpenTimestamps',
            'status' => $timestampProof ? 'submitted' : 'failed',
        ];
    }

    protected function calculateDocumentHash(string $documentPath): string
    {
        return hash_file('sha256', $documentPath);
    }

    protected function submitToOpenTimestamps(string $hash): ?string
    {
        if (! config('timestamp.opentimestamps.enabled', true)) {
            \Log::info('[OpenTimestamps] Disabled via config');

            return null;
        }

        try {
            $calendarUrl = config('timestamp.opentimestamps.calendar_url');
            $timeout = config('timestamp.opentimestamps.timeout', 10);

            \Log::info('[OpenTimestamps] Submitting hash', [
                'hash' => $hash,
                'url' => $calendarUrl,
            ]);

            // OpenTimestamps calendar server expects binary hash
            $response = Http::timeout($timeout)
                ->withBody(hex2bin($hash), 'application/octet-stream')
                ->post("{$calendarUrl}/digest");

            if ($response->successful()) {
                // Store the timestamp proof (OTS format)
                $proof = base64_encode($response->body());

                \Log::info('[OpenTimestamps] Submission successful', [
                    'proof_size' => strlen($proof),
                ]);

                return $proof;
            }

            \Log::warning('[OpenTimestamps] Submission failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            \Log::error('[OpenTimestamps] Submission error', [
                'error' => $e->getMessage(),
                'hash' => $hash,
            ]);

            return null;
        }
    }
}
