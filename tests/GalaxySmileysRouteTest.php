<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GalaxySmileysRouteTest extends TestCase
{
    const UUID = '6f1c2a34-1b2c-4d5e-8f90-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.galaxy_api' => [
            'enabled' => true,
            'base_url' => 'https://galaxy.test',
            'keycloak_url' => 'https://kc.test/auth',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'keycloak_client_secret' => 'shh',
            'redirect_uri' => 'https://shop.test/login-sso',
        ]]);
    }

    private function fakeGalaxy(array $body, int $status): void
    {
        Http::fake([
            'https://kc.test/*' => Http::response(['access_token' => 'svc-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response($body, $status),
        ]);
    }

    public function testBalanceIsReturnedAsWholeSmileys()
    {
        $this->fakeGalaxy([
            'status' => true,
            'statusCode' => 200,
            'result' => [
                'galaxyUuid' => self::UUID,
                'salesforceAccountId' => '001G500001JW73MIAT',
                'salesforceContactId' => '003G500001ABCDEFGH',
                'balance' => 750,
            ],
        ], 200);

        $this->get('/api/middleware/getSmileys/' . self::UUID . '?salesforceAccountId=001G500001JW73MIAT');

        $this->seeStatusCode(200);
        $this->seeJsonEquals([
            'success' => 1,
            'result' => 750,
            'salesforceAccountId' => '001G500001JW73MIAT',
        ]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'salesforceAccountId=001G500001JW73MIAT'));
    }

    public function testUnknownGalaxyUserIsFlagged()
    {
        $this->fakeGalaxy(['status' => false, 'statusCode' => 404, 'message' => 'Galaxy user not found'], 404);

        $this->get('/api/middleware/getSmileys/' . self::UUID);

        $this->seeStatusCode(200);
        $this->seeJson(['success' => 0, 'code' => 'GALAXY_USER_NOT_FOUND']);
    }

    public function testAmbiguousAccountIsFlagged()
    {
        $this->fakeGalaxy(['status' => false, 'statusCode' => 409, 'message' => 'salesforceAccountId is required'], 409);

        $this->get('/api/middleware/getSmileys/' . self::UUID);

        $this->seeStatusCode(200);
        $this->seeJson(['success' => 0, 'code' => 'GALAXY_ACCOUNT_UNAVAILABLE']);
    }

    public function testGalaxyOutageIsABadGateway()
    {
        $this->fakeGalaxy(['status' => false, 'statusCode' => 500, 'message' => 'Internal server error'], 500);

        $this->get('/api/middleware/getSmileys/' . self::UUID);

        $this->seeStatusCode(502);
    }

    public function testMissingPartnerScopeIsABadGatewayWithItsCode()
    {
        $this->fakeGalaxy([
            'status' => false,
            'statusCode' => 403,
            'message' => 'Missing partner scope: smileys:read',
            'code' => 'PARTNER_SCOPE_DENIED',
            'details' => ['missing' => ['smileys:read']],
        ], 403);

        $this->get('/api/middleware/getSmileys/' . self::UUID . '?salesforceAccountId=001G500001JW73MIAT');

        $this->seeStatusCode(502);
        $this->seeJson(['success' => 0, 'code' => 'GALAXY_PARTNER_SCOPE_DENIED']);
    }

    public function testAccountErrorsKeepTheirGalaxyCode()
    {
        $this->fakeGalaxy([
            'status' => false,
            'statusCode' => 403,
            'message' => 'The user is not an active contact of this Salesforce account',
            'code' => 'ACCOUNT_NOT_MEMBER',
        ], 403);

        $this->get('/api/middleware/getSmileys/' . self::UUID . '?salesforceAccountId=001G500001JW73MIAT');

        $this->seeStatusCode(200);
        $this->seeJson(['success' => 0, 'code' => 'GALAXY_ACCOUNT_NOT_MEMBER']);
    }
}
