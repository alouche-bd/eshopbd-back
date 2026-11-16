<?php

use Illuminate\Support\Facades\Http;

class GalaxyLoginUrlRouteTest extends TestCase
{
    public function testLoginUrlPointsAtKeycloak()
    {
        config(['services.galaxy_api' => [
            'mode' => 'new',
            'keycloak_url' => 'https://kc.test/auth',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'redirect_uri' => 'https://shop.test/login-sso',
        ]]);

        $this->get('/api/galaxy/login-url');

        $this->seeStatusCode(200);
        $this->seeJson(['success' => 1]);
        $this->assertStringStartsWith(
            'https://kc.test/auth/realms/s4a/protocol/openid-connect/auth?',
            json_decode($this->response->getContent(), true)['url']
        );
    }

    public function testLoginUrlIsUnavailableWhenTheFlagIsOff()
    {
        config(['services.galaxy_api' => ['mode' => 'legacy']]);

        $this->get('/api/galaxy/login-url');

        $this->seeStatusCode(404);
    }

    public function testCodeAndStateAreRequired()
    {
        config(['services.galaxy_api' => ['mode' => 'new']]);

        $this->post('/api/login-sso', ['token' => 'legacy-token']);

        $this->seeStatusCode(422);
    }

    public function testRejectedGalaxyCredentialsAnswer401()
    {
        config(['services.galaxy_api' => [
            'mode' => 'new',
            'keycloak_url' => 'https://kc.test/auth',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'keycloak_client_secret' => 'shh',
        ]]);
        Http::fake([
            'https://kc.test/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid user credentials'], 401),
        ]);

        $this->post('/api/reverse-login-sso', ['email' => 'dr@example.com', 'password' => 'wrong']);

        $this->seeStatusCode(401);
        $this->seeJson(['success' => 0]);
        Http::assertSent(fn ($request) => $request['grant_type'] === 'password' && $request['username'] === 'dr@example.com');
    }

    public function testUnknownLoginStateAnswers401()
    {
        config(['services.galaxy_api' => ['mode' => 'new']]);

        $this->post('/api/login-sso', ['code' => 'abc', 'state' => 'never-issued']);

        $this->seeStatusCode(401);
        $this->seeJson(['success' => 0]);
    }

    public function testUnregisteredPartnerClientIsNotReportedAsBadCredentials()
    {
        config(['services.galaxy_api' => [
            'mode' => 'new',
            'base_url' => 'https://galaxy.test',
            'keycloak_url' => 'https://kc.test/auth',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'keycloak_client_secret' => 'shh',
        ]]);
        Http::fake([
            'https://kc.test/*' => Http::response(['access_token' => 'user-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response([
                'status' => false,
                'statusCode' => 403,
                'message' => 'This client is not registered as a partner',
                'code' => 'PARTNER_CLIENT_UNKNOWN',
                'timestamp' => '2026-11-16T10:00:00.000Z',
            ], 403),
        ]);

        $this->post('/api/reverse-login-sso', ['email' => 'dr@example.com', 'password' => 'right']);

        $this->seeStatusCode(502);
        $this->seeJson(['success' => 0, 'code' => 'GALAXY_PARTNER_CLIENT_UNKNOWN']);
    }
}
