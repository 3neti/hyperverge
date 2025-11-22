<?php

namespace LBHurtado\HyperVerge\Actions\Results;

use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;
use LBHurtado\HyperVerge\Data\Modules\SelfieValidationModuleData;

/**
 * Extract image URLs from KYC verification results.
 * 
 * This action fetches KYC results and extracts all available image URLs
 * (ID card, cropped ID, selfie) into a structured array.
 * 
 * @example
 * $images = ExtractKYCImages::run(transactionId: 'user_123_abc');
 * 
 * // Returns:
 * // [
 * //     'id_card_full' => 'https://...',
 * //     'id_card_cropped' => 'https://...',
 * //     'selfie' => 'https://...',
 * // ]
 * 
 * @see https://documentation.hyperverge.co
 */
class ExtractKYCImages
{
    use AsAction;

    /**
     * The command signature for artisan usage.
     */
    public string $commandSignature = 'hyperverge:extract-images 
                                        {transactionId : The transaction ID}
                                        {--json : Output as JSON}';

    /**
     * The command description.
     */
    public string $commandDescription = 'Extract image URLs from KYC verification results';

    /**
     * Execute the action to extract image URLs.
     *
     * @param string $transactionId The transaction ID to extract images from
     * @param Model|null $context Campaign, CampaignSubmission, User for credential resolution
     * @return array Array of image URLs indexed by type
     */
    public function handle(string $transactionId, ?Model $context = null): array
    {
        // Fetch the KYC result
        $result = FetchKYCResult::run($transactionId, $context);
        
        $images = [];
        
        // Extract images from each module
        foreach ($result->modules as $module) {
            if ($module instanceof IdCardModuleData) {
                if ($module->imageUrl) {
                    $images['id_card_full'] = $module->imageUrl;
                }
                if ($module->croppedImageUrl) {
                    $images['id_card_cropped'] = $module->croppedImageUrl;
                }
                $images['id_card_country'] = $module->countrySelected ?? null;
                $images['id_card_document_type'] = $module->documentSelected ?? null;
            }
            
            if ($module instanceof SelfieValidationModuleData) {
                if ($module->imageUrl) {
                    $images['selfie'] = $module->imageUrl;
                }
            }
        }
        
        return $images;
    }

    /**
     * Get validation rules when used as a controller.
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Map HTTP request data to action parameters when used as controller.
     */
    public function mapToParameters(array $data): array
    {
        return [
            'transactionId' => $data['transaction_id'],
        ];
    }

    /**
     * Handle the action as a job.
     */
    public function asJob(string $transactionId, ?Model $context = null): void
    {
        $this->handle($transactionId, $context);
    }

    /**
     * Handle the action as a command.
     */
    public function asCommand(): int
    {
        $transactionId = $this->argument('transactionId');
        $images = $this->handle($transactionId);

        if ($this->option('json')) {
            $this->line(json_encode($images, JSON_PRETTY_PRINT));
        } else {
            $this->info('✅ Images Extracted Successfully!');
            $this->line('');
            
            if (isset($images['id_card_full'])) {
                $this->info('ID Card (Full):');
                $this->line('  ' . $images['id_card_full']);
                $this->line('');
            }
            
            if (isset($images['id_card_cropped'])) {
                $this->info('ID Card (Cropped):');
                $this->line('  ' . $images['id_card_cropped']);
                $this->line('');
            }
            
            if (isset($images['selfie'])) {
                $this->info('Selfie:');
                $this->line('  ' . $images['selfie']);
                $this->line('');
            }
            
            if (empty($images)) {
                $this->warn('No images found for this transaction.');
            } else {
                $this->line('Document Info:');
                $this->line('  Country: ' . ($images['id_card_country'] ?? 'N/A'));
                $this->line('  Type: ' . ($images['id_card_document_type'] ?? 'N/A'));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Get the response data when used as a controller.
     */
    public function jsonResponse(array $images): array
    {
        return [
            'success' => !empty($images),
            'images' => $images,
            'count' => count(array_filter($images, fn($v) => is_string($v) && str_starts_with($v, 'http'))),
        ];
    }
}
