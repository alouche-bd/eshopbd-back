<?php

use App\Services\Galaxy\GalaxyApiClient;
use App\Services\Galaxy\GalaxyApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GalaxyApiClientTest extends TestCase
{
    const TOKEN_URL = 'https://kc.test/auth/realms/s4a/protocol/openid-connect/token';
    const UUID = '6f1c2a34-1b2c-4d5e-8f90-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.galaxy_api' => [
            'enabled' => 'true',
            'base_url' => 'https://galaxy.test/',
            'keycloak_url' => 'https://kc.test/auth/',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'keycloak_client_secret' => 'shh',
            'redirect_uri' => 'https://shop.test/login-sso',
            'timeout' => 5,
        ]]);
    }

    private function client(): GalaxyApiClient
    {
        return new GalaxyApiClient();
    }

    private function envelope(array $result, int $statusCode = 200): array
    {
        return ['status' => true, 'statusCode' => $statusCode, 'result' => $result, 'timestamp' => '2026-11-15T10:00:00.000Z'];
    }

    public function testIsEnabledReadsTheFlag()
    {
        $this->assertTrue($this->client()->isEnabled());
        $this->assertFalse((new GalaxyApiClient(['enabled' => 'false']))->isEnabled());
        $this->assertFalse((new GalaxyApiClient([]))->isEnabled());
    }

    public function testServiceTokenUsesClientCredentialsAndIsCached()
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
        ]);

        $this->assertSame('svc-token', $this->client()->serviceToken());
        $this->assertSame('svc-token', $this->client()->serviceToken());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::TOKEN_URL
                && $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === 'biotech-dental-eshop'
                && $request['client_secret'] === 'shh';
        });
    }

    public function testSmileyBalanceUnwrapsTheEnvelope()
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
            'https://galaxy.test/integrations/eshop/users/*' => Http::response($this->envelope([
                'galaxyUuid' => self::UUID,
                'salesforceAccountId' => '001G500001JW73MIAT',
                'salesforceContactId' => '003G500001ABCDEFGH',
                'balance' => 250,
            ])),
        ]);

        $balance = $this->client()->smileyBalance(self::UUID, '001G500001JW73MIAT');

        $this->assertSame(250, $balance['balance']);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://galaxy.test/integrations/eshop/users/' . self::UUID . '/smileys/balance?salesforceAccountId=001G500001JW73MIAT'
                && $request->hasHeader('Authorization', 'Bearer svc-token');
        });
    }

    public function testDebitSmileysPostsWholeSmileys()
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
            'https://galaxy.test/integrations/eshop/users/*' => Http::response($this->envelope([
                'transactionUuid' => 'tx-1',
                'amount' => 500,
                'balance' => 0,
                'replayed' => false,
            ], 201), 201),
        ]);

        $debit = $this->client()->debitSmileys(self::UUID, 500, 'ESHOP-1');

        $this->assertSame('tx-1', $debit['transactionUuid']);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/smileys/debits')
                && $request->data() === ['amount' => 500, 'reference' => 'ESHOP-1'];
        });
    }

    public function testErrorEnvelopeBecomesAGalaxyApiException()
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response([
                'status' => false,
                'statusCode' => 404,
                'message' => 'Galaxy user not found',
                'timestamp' => '2026-11-15T10:00:00.000Z',
            ], 404),
        ]);

        try {
            $this->client()->smileyBalance(self::UUID);
            $this->fail('Expected a GalaxyApiException');
        } catch (GalaxyApiException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Galaxy user not found', $e->getMessage());
            $this->assertTrue($e->isNotFound());
        }
    }

    public function testKeycloakRejectionIsReportedAsUnauthorized()
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid user credentials'], 400),
        ]);

        try {
            $this->client()->passwordLogin('dr@example.com', 'wrong');
            $this->fail('Expected a GalaxyApiException');
        } catch (GalaxyApiException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertTrue($e->isUnauthorized());
            $this->assertSame('Invalid user credentials', $e->getMessage());
        }
    }

    public function testAuthorizationCodeFlowUsesPkceAndSingleUseState()
    {
        $url = $this->client()->authorizationUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://kc.test/auth/realms/s4a/protocol/openid-connect/auth?', $url);
        $this->assertSame('biotech-dental-eshop', $query['client_id']);
        $this->assertSame('https://shop.test/login-sso', $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'user-token', 'expires_in' => 300]),
        ]);

        $tokens = $this->client()->exchangeAuthorizationCode('the-code', $query['state']);
        $this->assertSame('user-token', $tokens['access_token']);

        Http::assertSent(function (Request $request) use ($query) {
            $verifier = $request['code_verifier'];
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            return $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'the-code'
                && $challenge === $query['code_challenge'];
        });

        $this->expectException(GalaxyApiException::class);
        $this->client()->exchangeAuthorizationCode('the-code', $query['state']);
    }

    public function testMeSendsTheUserToken()
    {
        Http::fake([
            'https://galaxy.test/integrations/eshop/me' => Http::response($this->envelope([
                'galaxyUuid' => self::UUID,
                'email' => 'dr@example.com',
            ])),
        ]);

        $profile = $this->client()->me('user-token');

        $this->assertSame('dr@example.com', $profile['email']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer user-token'));
    }

    public function testUnreachableGalaxyIsA503()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        try {
            $this->client()->health();
            $this->fail('Expected a GalaxyApiException');
        } catch (GalaxyApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }
}
