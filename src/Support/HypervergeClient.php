<?php

namespace LBHurtado\HyperVerge\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HypervergeClient
{
    public function __construct(
        protected array $config
    ) {
    }

    protected function baseRequest(): PendingRequest
    {
        return Http::withHeaders([
            'appId'  => $this->config['app_id'] ?? null,
            'appKey' => $this->config['app_key'] ?? null,
        ])
            ->timeout($this->config['timeout'] ?? 15)
            ->retry(2, 200);  // Retry up to 2 times with 200ms delay
    }

    public function request(string $method, string $endpoint, array $payload = []): array
    {
        $url = rtrim($this->config['base_url'] ?? '', '/') . '/' . ltrim($endpoint, '/');

        return $this->baseRequest()
            ->send($method, $url, ['json' => $payload])
            ->throw()
            ->json();
    }

    public function get(string $endpoint, array $query = []): array
    {
        $url = rtrim($this->config['base_url'] ?? '', '/') . '/' . ltrim($endpoint, '/');

        return $this->baseRequest()
            ->get($url, $query)
            ->throw()
            ->json();
    }

    public function post(string $endpoint, array $payload = []): array
    {
        return $this->request('POST', $endpoint, $payload);
    }

    public function put(string $endpoint, array $payload = []): array
    {
        return $this->request('PUT', $endpoint, $payload);
    }
}
