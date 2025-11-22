<?php

namespace LBHurtado\HyperVerge\Actions\Timestamp;

use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class VerifyBlockchainTimestamp
{
    use AsAction;

    /**
     * Verify if a blockchain timestamp has been confirmed.
     *
     * @param  string  $timestampProof  Base64-encoded OpenTimestamps proof
     * @param  string  $documentHash  SHA-256 hash of the document
     * @return array{
     *     confirmed: bool,
     *     blockchain: string,
     *     block_height: int|null,
     *     block_time: string|null,
     *     confirmations: int|null,
     *     status: string,
     *     message: string
     * }
     */
    public function handle(string $timestampProof, string $documentHash): array
    {
        try {
            // Decode the proof
            $proofBytes = base64_decode($timestampProof);
            
            if ($proofBytes === false) {
                return [
                    'confirmed' => false,
                    'blockchain' => 'Bitcoin',
                    'block_height' => null,
                    'block_time' => null,
                    'confirmations' => null,
                    'status' => 'invalid',
                    'message' => 'Invalid timestamp proof format',
                ];
            }

            // Query OpenTimestamps info endpoint
            $response = Http::timeout(config('hyperverge.timestamp.timeout', 30))
                ->post(config('hyperverge.timestamp.verify_url', 'https://finney.calendar.eternitywall.com/timestamp'), [
                    'hash' => $documentHash,
                ]);

            if (! $response->successful()) {
                return [
                    'confirmed' => false,
                    'blockchain' => 'Bitcoin',
                    'block_height' => null,
                    'block_time' => null,
                    'confirmations' => null,
                    'status' => 'pending',
                    'message' => 'Waiting for blockchain confirmation (1-24 hours)',
                ];
            }

            $data = $response->json();

            // Check if timestamp is confirmed
            if (isset($data['bitcoin']['height']) && $data['bitcoin']['height'] > 0) {
                return [
                    'confirmed' => true,
                    'blockchain' => 'Bitcoin',
                    'block_height' => $data['bitcoin']['height'],
                    'block_time' => $data['bitcoin']['blocktime'] ?? null,
                    'confirmations' => $data['bitcoin']['confirmations'] ?? null,
                    'status' => 'confirmed',
                    'message' => 'Timestamp confirmed on Bitcoin blockchain',
                ];
            }

            return [
                'confirmed' => false,
                'blockchain' => 'Bitcoin',
                'block_height' => null,
                'block_time' => null,
                'confirmations' => null,
                'status' => 'pending',
                'message' => 'Waiting for blockchain confirmation (1-24 hours)',
            ];
        } catch (\Exception $e) {
            return [
                'confirmed' => false,
                'blockchain' => 'Bitcoin',
                'block_height' => null,
                'block_time' => null,
                'confirmations' => null,
                'status' => 'error',
                'message' => 'Failed to verify timestamp: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get CLI command signature.
     */
    public function asCommand(): string
    {
        return 'hyperverge:verify-timestamp {proof} {hash}';
    }

    /**
     * Get CLI command description.
     */
    public function getCommandDescription(): string
    {
        return 'Verify blockchain timestamp confirmation status';
    }
}
