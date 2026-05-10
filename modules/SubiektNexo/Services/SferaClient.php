<?php

namespace Modules\SubiektNexo\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SferaClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim($this->getConfig('base_url', 'http://192.168.0.49:5005'), '/');
    }

    /**
     * Check if Sfera API is reachable.
     */
    public function health(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            return $response->json() ?? ['status' => 'error'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check Sfera connection status (is Subiekt connected).
     */
    public function status(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/sfera/status");

            return $response->json() ?? ['connected' => false];
        } catch (\Throwable $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Force connect to Subiekt.
     */
    public function connect(): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/api/sfera/connect");

            return $response->json() ?? ['connected' => false];
        } catch (\Throwable $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get products (towary) list from Subiekt.
     */
    public function getProducts(int $limit = 100): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/api/towary", ['limit' => $limit]);

            return $response->json() ?? ['items' => []];
        } catch (\Throwable $e) {
            Log::error('Sfera: getProducts failed', ['error' => $e->getMessage()]);

            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Get single product by symbol.
     */
    public function getProduct(string $symbol): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/towary/{$symbol}");
            $data = $response->json();

            if (! $data || ! ($data['found'] ?? false)) {
                return null;
            }

            return $data['item'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Sfera: getProduct failed', ['symbol' => $symbol, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get contractors (kontrahenci) list.
     */
    public function getContractors(int $limit = 100): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/api/kontrahenci", ['limit' => $limit]);

            return $response->json() ?? ['items' => []];
        } catch (\Throwable $e) {
            Log::error('Sfera: getContractors failed', ['error' => $e->getMessage()]);

            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Get single contractor by ID.
     */
    public function getContractor(int $id): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/kontrahenci/{$id}");

            return $response->json() ?? null;
        } catch (\Throwable $e) {
            Log::error('Sfera: getContractor failed', ['id' => $id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function getConfig(string $key, $default = null)
    {
        $config = \App\Models\IntegrationConfig::where('system_type', 'subiekt_nexo')
            ->where('is_active', true)
            ->first();

        if ($config && $config->api_config) {
            return $config->api_config[$key] ?? $default;
        }

        return $default;
    }
}
