<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagarmeClient
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.pagarme.api_url', 'https://api.pagar.me/core/v5');
        $this->secretKey = config('services.pagarme.secret_key') ?? '';
    }

    public function post(string $endpoint, array $data = [], ?string $idempotencyKey = null): array
    {
        $request = Http::withBasicAuth($this->secretKey, '');

        if ($idempotencyKey) {
            $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post("{$this->baseUrl}{$endpoint}", $data);

        if (!$response->successful()) {
            Log::error('Pagar.me API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \RuntimeException("Pagar.me API error: {$response->status()}");
        }

        return $response->json();
    }

    public function get(string $endpoint, array $query = []): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}{$endpoint}", $query);

        if (!$response->successful()) {
            Log::error('Pagar.me API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \RuntimeException("Pagar.me API error: {$response->status()}");
        }

        return $response->json();
    }

    public function patch(string $endpoint, array $data = []): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->patch("{$this->baseUrl}{$endpoint}", $data);

        if (!$response->successful()) {
            Log::error('Pagar.me API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \RuntimeException("Pagar.me API error: {$response->status()}");
        }

        return $response->json();
    }

    public function delete(string $endpoint): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->delete("{$this->baseUrl}{$endpoint}");

        if (!$response->successful()) {
            Log::error('Pagar.me API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \RuntimeException("Pagar.me API error: {$response->status()}");
        }

        return $response->json();
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.pagarme.webhook_secret', '');
        if (!$secret) {
            \Illuminate\Support\Facades\Log::critical('Pagar.me webhook secret not configured — rejecting webhook');
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
