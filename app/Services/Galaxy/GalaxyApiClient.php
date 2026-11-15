<?php

namespace App\Services\Galaxy;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GalaxyApiClient
{
    private const SERVICE_TOKEN_CACHE_KEY = 'galaxy_api:service_token';
    private const LOGIN_STATE_CACHE_PREFIX = 'galaxy_api:login_state:';
    private const LOGIN_STATE_TTL_SECONDS = 600;

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('services.galaxy_api', []);
    }

    public function isEnabled(): bool
    {
        return filter_var($this->config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function serviceToken(): string
    {
        $cached = Cache::get(self::SERVICE_TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->requestToken(['grant_type' => 'client_credentials']);
        $ttl = max(((int) ($token['expires_in'] ?? 60)) - 30, 1);
        Cache::put(self::SERVICE_TOKEN_CACHE_KEY, $token['access_token'], $ttl);

        return $token['access_token'];
    }

    public function passwordLogin(string $email, string $password): array
    {
        return $this->requestToken([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
            'scope' => 'openid email profile',
        ]);
    }

    public function authorizationUrl(): string
    {
        $state = Str::random(40);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        Cache::put(self::LOGIN_STATE_CACHE_PREFIX . $state, $verifier, self::LOGIN_STATE_TTL_SECONDS);

        return $this->realmUrl('protocol/openid-connect/auth') . '?' . http_build_query([
            'client_id' => $this->config['keycloak_client_id'] ?? '',
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'redirect_uri' => $this->config['redirect_uri'] ?? '',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeAuthorizationCode(string $code, string $state): array
    {
        $verifier = Cache::pull(self::LOGIN_STATE_CACHE_PREFIX . $state);
        if (!is_string($verifier) || $verifier === '') {
            throw new GalaxyApiException('Unknown or expired login state', 401);
        }

        return $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'] ?? '',
            'code_verifier' => $verifier,
        ]);
    }

    public function me(string $userAccessToken): array
    {
        return $this->call('GET', 'integrations/eshop/me', $userAccessToken);
    }

    public function smileyBalance(string $galaxyUuid, ?string $salesforceAccountId = null): array
    {
        return $this->call(
            'GET',
            'integrations/eshop/users/' . rawurlencode($galaxyUuid) . '/smileys/balance',
            $this->serviceToken(),
            ['query' => array_filter(['salesforceAccountId' => $salesforceAccountId])]
        );
    }

    public function debitSmileys(string $galaxyUuid, int $amount, string $reference, ?string $salesforceAccountId = null): array
    {
        return $this->call(
            'POST',
            'integrations/eshop/users/' . rawurlencode($galaxyUuid) . '/smileys/debits',
            $this->serviceToken(),
            ['json' => array_filter([
                'amount' => $amount,
                'reference' => $reference,
                'salesforceAccountId' => $salesforceAccountId,
            ], fn ($value) => $value !== null && $value !== '')]
        );
    }

    public function health(): array
    {
        return $this->call('GET', 'health', null);
    }

    private function requestToken(array $form): array
    {
        try {
            $response = $this->http()->asForm()->post($this->realmUrl('protocol/openid-connect/token'), $form + [
                'client_id' => $this->config['keycloak_client_id'] ?? '',
                'client_secret' => $this->config['keycloak_client_secret'] ?? '',
            ]);
        } catch (ConnectionException $e) {
            throw new GalaxyApiException('Keycloak is unreachable', 503, [], $e);
        }

        $body = $response->json();
        if (!$response->successful() || !is_array($body) || empty($body['access_token'])) {
            $message = is_array($body) ? (string) ($body['error_description'] ?? $body['error'] ?? 'Keycloak token request failed') : 'Keycloak token request failed';
            throw new GalaxyApiException($message, $response->status() === 400 ? 401 : $response->status());
        }

        return $body;
    }

    private function call(string $method, string $path, ?string $token, array $options = []): array
    {
        $request = $this->http()->acceptJson();
        if ($token !== null) {
            $request = $request->withToken($token);
        }

        $url = rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');

        try {
            $response = $request->send($method, $url, $options);
        } catch (ConnectionException $e) {
            throw new GalaxyApiException('galaxy-api is unreachable', 503, [], $e);
        }

        return $this->unwrap($response);
    }

    private function unwrap(Response $response): array
    {
        $body = $response->json();

        if (is_array($body) && ($body['status'] ?? null) === true && array_key_exists('result', $body)) {
            return is_array($body['result']) ? $body['result'] : ['value' => $body['result']];
        }

        if ($response->successful() && is_array($body) && !array_key_exists('status', $body)) {
            return $body;
        }

        $message = is_array($body) && isset($body['message']) ? (string) $body['message'] : 'galaxy-api request failed';
        $status = is_array($body) && isset($body['statusCode']) ? (int) $body['statusCode'] : $response->status();
        $errors = is_array($body) && isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : [];
        $code = is_array($body) && isset($body['code']) && is_string($body['code']) ? $body['code'] : null;
        $details = is_array($body) && isset($body['details']) && is_array($body['details']) ? $body['details'] : [];

        throw GalaxyApiException::fromErrorBody($status, $message, $code, $errors, $details);
    }

    private function realmUrl(string $suffix): string
    {
        return rtrim((string) ($this->config['keycloak_url'] ?? ''), '/')
            . '/realms/' . rawurlencode((string) ($this->config['keycloak_realm'] ?? 's4a'))
            . '/' . $suffix;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) ($this->config['timeout'] ?? 15));
    }
}
