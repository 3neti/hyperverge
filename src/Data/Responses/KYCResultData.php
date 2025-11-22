<?php

namespace LBHurtado\HyperVerge\Data\Responses;

use LBHurtado\HyperVerge\Data\HypervergeResponseData;
use LBHurtado\HyperVerge\Data\Modules\HypervergeModuleData;
use LBHurtado\HyperVerge\Factories\ModuleFactory;

/**
 * Response DTO for the HyperVerge KYC Result API.
 * Response structure: {status, statusCode, metadata, result}
 * Where result contains: {results[], applicationStatus, ...}
 */
class KYCResultData extends HypervergeResponseData
{
    /**
     * @param  HypervergeModuleData[]  $modules
     */
    public function __construct(
        public string $status,
        public string $statusCode,
        public ?string $requestId,
        public ?string $transactionId,
        public string $applicationStatus,
        public array $modules,
        array $raw,
        array $meta = [],
    ) {
        parent::__construct(raw: $raw, meta: $meta);
    }

    /**
     * Build from a raw HyperVerge response.
     * Expects structure: {status, statusCode, metadata: {requestId, transactionId}, result: {results[], applicationStatus}}
     */
    public static function fromHyperverge(array $response, array $meta = []): self
    {
        $modules = [];
        $results = $response['result']['results'] ?? [];
        foreach ($results as $module) {
            $modules[] = ModuleFactory::make($module);
        }

        return new self(
            status: (string)($response['status'] ?? ''),
            statusCode: (string)($response['statusCode'] ?? ''),
            requestId: $response['metadata']['requestId'] ?? null,
            transactionId: $response['metadata']['transactionId'] ?? null,
            applicationStatus: (string)($response['result']['applicationStatus'] ?? ''),
            modules: $modules,
            raw: $response,
            meta: $meta,
        );
    }

    /**
     * Convenient accessor to get a module by name.
     */
    public function getModuleByName(string $name): ?HypervergeModuleData
    {
        foreach ($this->modules as $module) {
            if ($module->module === $name) {
                return $module;
            }
        }

        return null;
    }
}
