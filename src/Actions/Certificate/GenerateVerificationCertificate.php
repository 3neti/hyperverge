<?php

namespace LBHurtado\HyperVerge\Actions\Certificate;

use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\HyperVerge\Actions\Certificate\Layouts\DefaultCertificateLayout;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;

class GenerateVerificationCertificate
{
    use AsAction;

    /**
     * Generate a verification certificate PDF from KYC data.
     *
     * @param  Model  $model  - CampaignSubmission or User
     * @param  string  $transactionId  - HyperVerge transaction ID
     * @param  array  $options  - Additional options (layout, branding, etc)
     * @return string - Path to generated PDF
     */
    public function handle(
        Model $model,
        string $transactionId,
        array $options = []
    ): string {
        // 1. Fetch KYC result
        $kycResult = FetchKYCResult::run($transactionId, $model);

        // 2. Prepare certificate data
        $data = CertificateData::fromKYCResult($kycResult, $model, $options);

        // 3. Generate PDF
        $layoutClass = $options['layout'] ?? DefaultCertificateLayout::class;
        $layout = new $layoutClass;
        $pdfPath = $layout->render($data);

        return $pdfPath;
    }
}
