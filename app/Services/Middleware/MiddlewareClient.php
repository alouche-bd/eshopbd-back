<?php

namespace App\Services\Middleware;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Reusable HTTP client for the LaGalaxy / Sage X3 middleware service.
 *
 * Replaces the per-controller new Guzzle / Http::withToken() pattern so that
 * Bearer-auth, base URI, and verify=false handling live in one place.
 */
class MiddlewareClient
{
    private string $baseUri;
    private string $secret;
    private Client $client;

    public function __construct()
    {
        $this->baseUri = (string) config('services.middleware.base_uri');
        $this->secret  = (string) config('services.middleware.secret');
        $this->client  = new Client([
            'base_uri' => $this->baseUri,
            'verify'   => false,
            'timeout'  => 30,
        ]);
    }

    /**
     * GET an endpoint, return decoded array.
     *
     * @throws Exception on network failure or non-2xx response.
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->client->request('GET', $path, [
            'headers' => $this->headers(),
            'query'   => $query,
        ]);

        return $this->decode($response->getBody()->getContents());
    }

    public function post(string $path, array $body): array
    {
        $response = $this->client->request('POST', $path, [
            'headers' => $this->headers(),
            'json'    => $body,
        ]);

        return $this->decode($response->getBody()->getContents());
    }

    public function put(string $path, array $body): array
    {
        $response = $this->client->request('PUT', $path, [
            'headers' => $this->headers(),
            'json'    => $body,
        ]);

        return $this->decode($response->getBody()->getContents());
    }

    /**
     * GET that swallows network errors and returns the decoded body when
     * status is 2xx, or null otherwise. Use for non-critical reads.
     */
    public function tryGet(string $path): ?array
    {
        try {
            return $this->get($path);
        } catch (Exception $e) {
            Log::warning('MiddlewareClient::tryGet failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->secret,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'raw' => $body];
        }
        return $decoded;
    }
}
