<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class GalaxyLegacyModeTest extends TestCase
{
    const UUID = '6f1c2a34-1b2c-4d5e-8f90-123456789abc';
    const LEGACY_TOKEN_URL = 'https://legacy-oauth.test/auth/realms/s4a/protocol/openid-connect/token';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.middleware.base_uri' => 'https://middleware.test',
            'services.s4a.base_uri' => 'https://legacy-s4a.test/',
            'services.oauth.base_oauth' => 'https://legacy-oauth.test/',
            'services.oauth.id_oauth' => 'legacy-client',
            'services.oauth.secret_oauth' => 'legacy-secret',
        ]);
    }

    private function fakeRemotes(array $galaxyApi = []): void
    {
        Http::fake($galaxyApi + [
            self::LEGACY_TOKEN_URL => Http::response(['access_token' => 'legacy-token', 'expires_in' => 300]),
            'https://legacy-s4a.test/smileys/total/*' => Http::response(['code' => 'OK', 'result' => 640]),
            'https://legacy-s4a.test/smileys/consume/*' => Http::response(['code' => 'OK']),
            'https://middleware.test/*' => Http::response(['ok' => true]),
            '*' => Http::response(['unexpected' => true], 599),
        ]);
    }

    private function jsonBody(Request $request): array
    {
        return (array) json_decode($request->body(), true);
    }

    private function useGalaxyConfig(array $overrides): void
    {
        config(['services.galaxy_api' => $overrides + [
            'base_url' => 'https://galaxy.test',
            'keycloak_url' => 'https://kc.test/auth',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'keycloak_client_secret' => 'shh',
            'redirect_uri' => 'https://shop.test/login-sso',
        ]]);
    }

    private function assertNothingSentToGalaxyApi(): void
    {
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://galaxy.test')
            || str_starts_with($request->url(), 'https://kc.test'));
    }

    public function testTheDefaultModeIsLegacy()
    {
        $this->assertSame('legacy', config('services.galaxy_api.mode'));
        $this->assertFalse(app(\App\Services\Galaxy\GalaxyApiClient::class)->isNewMode());
    }

    public function legacyModes(): array
    {
        return [
            'unset' => [[]],
            'null' => [['mode' => null]],
            'empty' => [['mode' => '']],
            'legacy' => [['mode' => 'legacy']],
            'unknown' => [['mode' => 'galaxy-api']],
            'old enabled flag' => [['enabled' => true]],
        ];
    }

    /**
     * @dataProvider legacyModes
     */
    public function testLegacyModeReadsTheBalanceFromTheLegacyApi(array $galaxyConfig)
    {
        $this->useGalaxyConfig($galaxyConfig);
        $this->fakeRemotes();

        $this->get('/api/middleware/getSmileys/' . self::UUID . '?salesforceAccountId=001G500001JW73MIAT');

        $this->seeStatusCode(200);
        $this->assertSame(['code' => 'OK', 'result' => 640], json_decode($this->response->getContent(), true));
        Http::assertSent(fn (Request $request) => $request->url() === self::LEGACY_TOKEN_URL
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'legacy-client');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://legacy-s4a.test/smileys/total/' . self::UUID
            && $request->hasHeader('Authorization', 'Bearer legacy-token'));
        $this->assertNothingSentToGalaxyApi();
    }

    public function testLegacyModeDebitsSmileysOnTheLegacyApi()
    {
        $this->useGalaxyConfig([]);
        $this->fakeRemotes();

        $this->post('/api/middleware/post-order', [
            'id' => 'cart-1',
            'clientcode' => 'SF00016427',
            'smileyamount' => 120,
            'uuid' => self::UUID,
        ]);

        $this->seeStatusCode(200);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://legacy-s4a.test/smileys/consume/' . self::UUID
            && ($this->jsonBody($request)['act'] ?? null) === 'debit'
            && ($this->jsonBody($request)['amount'] ?? null) === 120);
        $this->assertNothingSentToGalaxyApi();
    }

    public function testLegacyModeHasNoKeycloakLoginUrl()
    {
        $this->useGalaxyConfig([]);

        $this->get('/api/galaxy/login-url');

        $this->seeStatusCode(404);
    }

    public function testNewModeReadsTheBalanceFromGalaxyApi()
    {
        $this->useGalaxyConfig(['mode' => 'new']);
        $this->fakeRemotes([
            'https://kc.test/*' => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response(['status' => true, 'statusCode' => 200, 'result' => [
                'galaxyUuid' => self::UUID,
                'salesforceAccountId' => '001G500001JW73MIAT',
                'balance' => 750,
            ]]),
        ]);

        $this->get('/api/middleware/getSmileys/' . self::UUID . '?salesforceAccountId=001G500001JW73MIAT');

        $this->seeStatusCode(200);
        $this->seeJson(['success' => 1, 'result' => 750]);
        Http::assertSent(fn (Request $request) => str_starts_with(
            $request->url(),
            'https://galaxy.test/integrations/eshop/users/' . self::UUID . '/smileys/balance'
        ));
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://legacy-s4a.test'));
    }

    public function testNewModeDebitsSmileysOnGalaxyApi()
    {
        $this->useGalaxyConfig(['mode' => 'new']);
        $this->fakeRemotes([
            'https://kc.test/*' => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response(['status' => true, 'statusCode' => 201, 'result' => [
                'transactionUuid' => 'tx-1',
                'amount' => 120,
                'balance' => 520,
                'replayed' => false,
            ]]),
        ]);

        $this->post('/api/middleware/post-order', [
            'id' => 'cart-1',
            'clientcode' => 'SF00016427',
            'smileyamount' => 120,
            'uuid' => self::UUID,
        ]);

        $this->seeStatusCode(200);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://galaxy.test/integrations/eshop/users/' . self::UUID . '/smileys/debits'
            && ($this->jsonBody($request)['amount'] ?? null) === 120
            && ($this->jsonBody($request)['reference'] ?? null) === 'cart-1');
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://legacy-s4a.test'));
    }
}
