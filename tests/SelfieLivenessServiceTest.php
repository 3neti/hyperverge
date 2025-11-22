<?php

use LBHurtado\HyperVerge\Data\Responses\SelfieLivenessResponseData;
use LBHurtado\HyperVerge\Services\SelfieLivenessService;
use LBHurtado\HyperVerge\Support\HypervergeClient;
use Illuminate\Support\Facades\Http;

it('can verify selfie liveness', function () {
    Http::fake([
        '*/v1/selfie/liveness' => Http::response([
            'result' => [
                'isLive' => true,
                'quality' => [
                    'blur' => false,
                    'facePresent' => true,
                ],
            ],
        ], 200),
    ]);

    $client = new HypervergeClient([
        'base_url' => 'https://api.hyperverge.co',
        'app_id' => 'test_app_id',
        'app_key' => 'test_app_key',
        'timeout' => 30,
    ]);

    $service = new SelfieLivenessService($client);
    $result = $service->verify('fake_base64_image');

    expect($result)->toBeInstanceOf(SelfieLivenessResponseData::class);
    expect($result->isLive)->toBeTrue();
    expect($result->quality)->toBeArray();
});

it('handles liveness failure', function () {
    Http::fake([
        '*/v1/selfie/liveness' => Http::response([
            'result' => [
                'isLive' => false,
                'quality' => [
                    'blur' => true,
                ],
            ],
        ], 200),
    ]);

    $client = new HypervergeClient([
        'base_url' => 'https://api.hyperverge.co',
        'app_id' => 'test_app_id',
        'app_key' => 'test_app_key',
        'timeout' => 30,
    ]);

    $service = new SelfieLivenessService($client);
    $service->verify('fake_base64_image');
})->throws(\Hyperverge\Exceptions\LivelinessFailedException::class);
