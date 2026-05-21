<?php

namespace App\Services\Distributor;

use App\Constants\UserType;
use App\Models\User;
use App\Services\Middleware\MiddlewareClient;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * After every successful login, call the middleware /lireInfoClient endpoint
 * to refresh the user's Sage profile: billing country, currency, sage code,
 * representative, and the two preconfigured addresses.
 *
 * Determines distributor status:
 *   facturation.codepays !== "FR" AND client.type IN distributor sage types.
 */
class ClientSyncService
{
    public function __construct(private MiddlewareClient $middleware) {}

    /**
     * Sync the user against Sage. Returns the (possibly mutated) User. Never
     * throws — login flow must continue even if middleware is down.
     */
    public function syncFromSage(User $user): User
    {
        $clientCode = $user->codeclientGC ?: $user->sage_client_code;
        if (!$clientCode) {
            return $user;
        }

        $base = config('x3.base');
        $path = sprintf('/api/v3/lireInfoClient/%s/%s', rawurlencode($base), rawurlencode($clientCode));

        $payload = $this->middleware->tryGet($path);
        if (!$payload || empty($payload['success']) || empty($payload['client'])) {
            // Fallback to the legacy non-v3 endpoint kept for compatibility.
            $legacyPath = sprintf('/api/lireInfoClient/%s/%s', rawurlencode($base), rawurlencode($clientCode));
            $payload = $this->middleware->tryGet($legacyPath);
        }

        if (!$payload || empty($payload['client'])) {
            Log::warning('ClientSyncService: empty Sage payload', [
                'user_id'     => $user->id,
                'client_code' => $clientCode,
            ]);
            return $user;
        }

        $client      = $payload['client'];
        $facturation = $client['facturation'] ?? [];
        $livraison   = $client['livraison']   ?? [];

        $billingCountry = (string) ($facturation['codepays'] ?? '');
        $sageType       = (string) ($client['type'] ?? '');

        $user->sage_client_code         = (string) ($client['code'] ?? $clientCode);
        $user->raisonsociale            = $client['raisonsociale'] ?? $user->raisonsociale;
        $user->currency                 = (string) ($client['devise'] ?? $user->currency);
        $user->representative_code      = (string) ($client['representantcode'] ?? '');
        $user->representative_name      = (string) ($client['representant'] ?? '');
        $user->billing_country_code     = $billingCountry;
        $user->sage_facturation_address = $facturation ?: null;
        $user->sage_livraison_address   = $livraison ?: null;
        $user->sage_synced_at           = Carbon::now();

        // Distributor detection — never downgrade an ADV_INTER user.
        if ($user->user_type !== UserType::ADV_INTER) {
            if ($this->qualifiesAsDistributor($billingCountry, $sageType)) {
                $user->user_type = UserType::DISTRIBUTEUR;
            } elseif ($user->user_type === UserType::DISTRIBUTEUR) {
                // Country/type changed in Sage: revert to whatever Sage qualité tells us.
                $user->user_type = (string) ($client['qualite'] ?? $user->user_type);
            }
        }

        try {
            $user->save();
        } catch (Exception $e) {
            Log::error('ClientSyncService: save failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $user;
    }

    private function qualifiesAsDistributor(string $billingCountry, string $sageType): bool
    {
        if ($billingCountry === '' || strtoupper($billingCountry) === 'FR') {
            return false;
        }
        return in_array(strtoupper($sageType), UserType::DISTRIBUTOR_SAGE_TYPES, true);
    }
}
