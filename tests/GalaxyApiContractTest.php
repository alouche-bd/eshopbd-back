<?php

use App\Services\Galaxy\GalaxyAccountAmbiguousException;
use App\Services\Galaxy\GalaxyApiClient;
use App\Services\Galaxy\GalaxyApiException;
use App\Services\Galaxy\GalaxyPartnerAuthorizationException;
use App\Services\Galaxy\GalaxyPartnerUser;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GalaxyApiContractTest extends TestCase
{
    const UUID = '6f1c2a34-1b2c-4d5e-8f90-123456789abc';
    const ACCOUNT = '001G500001JW73MIAT';
    const RELIED_ON_USER_FIELDS = ['galaxyUuid', 'email', 'isActive'];

    private static ?array $openApi = null;
    private static ?array $fixtures = null;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.galaxy_api' => [
            'mode' => 'new',
            'base_url' => 'https://galaxy.test',
            'keycloak_url' => 'https://kc.test/auth',
            'keycloak_realm' => 's4a',
            'keycloak_client_id' => 'biotech-dental-eshop',
            'keycloak_client_secret' => 'shh',
            'redirect_uri' => 'https://shop.test/login-sso',
        ]]);
    }

    private static function openApi(): array
    {
        return self::$openApi ??= json_decode(file_get_contents(__DIR__ . '/fixtures/galaxy-api/openapi-eshop.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function fixtures(): array
    {
        return self::$fixtures ??= json_decode(file_get_contents(__DIR__ . '/fixtures/galaxy-api/responses.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function fixture(string $name): array
    {
        return self::fixtures()[$name];
    }

    private static function schema(string $name): array
    {
        return self::openApi()['schemas'][$name];
    }

    private function schemaViolations(array $schema, $value, string $path = '$'): array
    {
        if ($value === null) {
            return !empty($schema['nullable']) ? [] : [$path . ' is null'];
        }

        $violations = [];
        $type = $schema['type'] ?? null;
        $typeOk = match ($type) {
            'object' => is_array($value) && ($value === [] || array_keys($value) !== range(0, count($value) - 1)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default => true,
        };
        if (!$typeOk) {
            return [$path . ' is not ' . $type];
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $violations[] = $path . ' is not one of ' . implode(', ', $schema['enum']);
        }
        if (($schema['format'] ?? null) === 'uuid' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $violations[] = $path . ' is not a uuid';
        }
        if ($type === 'object') {
            foreach ($schema['required'] ?? [] as $required) {
                if (!array_key_exists($required, $value)) {
                    $violations[] = $path . '.' . $required . ' is missing';
                }
            }
            foreach ($value as $key => $item) {
                if (!isset($schema['properties'][$key])) {
                    $violations[] = $path . '.' . $key . ' is not declared';
                    continue;
                }
                $violations = array_merge($violations, $this->schemaViolations($schema['properties'][$key], $item, $path . '.' . $key));
            }
        }

        return $violations;
    }

    private function assertMatchesSchema(string $schemaName, $value): void
    {
        $this->assertSame([], $this->schemaViolations(self::schema($schemaName), $value), $schemaName);
    }

    private function operation(string $method, string $path): array
    {
        $operation = self::openApi()['paths'][$path][strtolower($method)] ?? null;
        $this->assertIsArray($operation, $method . ' ' . $path . ' is not in the galaxy-api OpenAPI document');

        return $operation;
    }

    private function fakeGalaxy(string $fixture): void
    {
        $response = self::fixture($fixture);
        Http::swap(new Factory());
        Http::fake([
            'https://kc.test/*' => Http::response(['access_token' => 'kc-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response($response['body'], $response['status']),
        ]);
    }

    private function catchGalaxy(callable $call): GalaxyApiException
    {
        try {
            $call();
        } catch (GalaxyApiException $e) {
            return $e;
        }
        $this->fail('Expected a GalaxyApiException');
    }

    public function testFixturesComeFromTheRecordedGalaxyApiCommit()
    {
        $this->assertSame('6e6fc05', self::openApi()['source']['commit']);
        $this->assertSame('6e6fc05', self::fixtures()['source']['commit']);
    }

    public function testSuccessFixturesMatchTheOpenApiSchemasInTheEnvelope()
    {
        foreach (self::fixtures() as $name => $fixture) {
            if ($name === 'source' || !isset($fixture['schema'])) {
                continue;
            }
            $body = $fixture['body'];
            $this->assertTrue($body['status'], $name);
            $this->assertSame($fixture['status'], $body['statusCode'], $name);
            $this->assertArrayHasKey('timestamp', $body, $name);
            $this->assertMatchesSchema($fixture['schema'], $body['result']);
        }
    }

    public function testErrorFixturesUseTheGalaxyErrorEnvelope()
    {
        foreach (self::fixtures() as $name => $fixture) {
            if ($name === 'source' || isset($fixture['schema'])) {
                continue;
            }
            $body = $fixture['body'];
            $this->assertFalse($body['status'], $name);
            $this->assertSame($fixture['status'], $body['statusCode'], $name);
            $this->assertIsString($body['message'], $name);
            $this->assertIsString($body['code'], $name);
            $this->assertArrayHasKey('timestamp', $body, $name);
            $this->assertArrayNotHasKey('result', $body, $name);
        }
    }

    public function testTheSchemaCheckRejectsADriftedPayload()
    {
        $result = self::fixture('me')['body']['result'];
        unset($result['isActive']);
        $result['role'] = 'DENTIST';

        $violations = $this->schemaViolations(self::schema('PartnerUserDto'), $result);

        $this->assertContains('$.isActive is missing', $violations);
        $this->assertContains('$.role is not one of ' . implode(', ', self::schema('PartnerUserDto')['properties']['role']['enum']), $violations);
    }

    public function testMeIsADeclaredRouteAndMapsThePartnerUserDto()
    {
        $this->operation('GET', '/integrations/eshop/me');
        $this->fakeGalaxy('me');

        $user = (new GalaxyApiClient())->me('user-token');

        $this->assertInstanceOf(GalaxyPartnerUser::class, $user);
        $this->assertSame(self::fixture('me')['body']['result'], (array) $user);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://galaxy.test/integrations/eshop/me'
            && $request->hasHeader('Authorization', 'Bearer user-token'));
    }

    public function testPartnerUserExposesEveryPartnerUserDtoField()
    {
        $schema = self::schema('PartnerUserDto');
        $exposed = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(GalaxyPartnerUser::class))->getProperties());

        $this->assertEqualsCanonicalizing($schema['required'], $exposed);
        $this->assertSame($schema['properties']['role']['enum'], GalaxyPartnerUser::ROLES);
    }

    public function testMeAcceptsANullBillingCountry()
    {
        $this->fakeGalaxy('meWithoutBillingCountry');

        $user = (new GalaxyApiClient())->me('user-token');

        $this->assertNull($user->billingCountry);
        $this->assertSame('LAB', $user->role);
    }

    public function testMeRefusesAPayloadWithoutAFieldTheEshopReliesOn()
    {
        foreach (self::RELIED_ON_USER_FIELDS as $field) {
            $this->assertContains($field, self::schema('PartnerUserDto')['required'], $field);

            $fixture = self::fixture('me');
            unset($fixture['body']['result'][$field]);
            Http::swap(new Factory());
            Http::fake(['https://galaxy.test/*' => Http::response($fixture['body'], 200)]);

            $e = $this->catchGalaxy(fn () => (new GalaxyApiClient())->me('user-token'));
            $this->assertSame(502, $e->getStatusCode(), $field);
            $this->assertStringContainsString($field, $e->getMessage());
        }
    }

    public function testBalanceIsADeclaredRouteWithTheSalesforceAccountQuery()
    {
        $operation = $this->operation('GET', '/integrations/eshop/users/{galaxyUuid}/smileys/balance');
        $query = array_column(array_filter($operation['parameters'], fn ($p) => $p['in'] === 'query'), 'required', 'name');
        $this->assertSame(['salesforceAccountId' => false], $query);
        $this->fakeGalaxy('balance');

        $balance = (new GalaxyApiClient())->smileyBalance(self::UUID, self::ACCOUNT);

        $this->assertSame(self::fixture('balance')['body']['result'], $balance);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://galaxy.test/integrations/eshop/users/' . self::UUID . '/smileys/balance?salesforceAccountId=' . self::ACCOUNT
            && $request->hasHeader('Authorization', 'Bearer kc-token'));
    }

    public function testBalanceWithoutAccountSendsNoQuery()
    {
        $this->fakeGalaxy('balance');

        (new GalaxyApiClient())->smileyBalance(self::UUID);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://galaxy.test/')
            && $request->url() === 'https://galaxy.test/integrations/eshop/users/' . self::UUID . '/smileys/balance');
    }

    public function testDebitBodyMatchesTheDebitSchemaAndTheResponseIsMapped()
    {
        $operation = $this->operation('POST', '/integrations/eshop/users/{galaxyUuid}/smileys/debits');
        $this->assertSame('#/components/schemas/EshopSmileyDebitDto', $operation['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertArrayHasKey('201', $operation['responses']);
        $this->fakeGalaxy('debit');

        $debit = (new GalaxyApiClient())->debitSmileys(self::UUID, 500, 'CART-42', self::ACCOUNT);

        $this->assertSame(self::fixture('debit')['body']['result'], $debit);
        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || !str_ends_with($request->url(), '/smileys/debits')) {
                return false;
            }
            $this->assertMatchesSchema('EshopSmileyDebitDto', $request->data());
            return $request->data() === ['amount' => 500, 'reference' => 'CART-42', 'salesforceAccountId' => self::ACCOUNT];
        });
    }

    public function testBalanceWithoutTheBalanceFieldIsRefused()
    {
        $fixture = self::fixture('balance');
        unset($fixture['body']['result']['balance']);
        Http::fake([
            'https://kc.test/*' => Http::response(['access_token' => 'kc-token', 'expires_in' => 300]),
            'https://galaxy.test/*' => Http::response($fixture['body'], 200),
        ]);

        $e = $this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID, self::ACCOUNT));

        $this->assertSame(502, $e->getStatusCode());
    }

    public function testPartnerRefusalsAreTypedAndNotCredentialErrors()
    {
        $this->fakeGalaxy('partnerClientUnknown');
        $unknown = $this->catchGalaxy(fn () => (new GalaxyApiClient())->me('user-token'));
        $this->assertInstanceOf(GalaxyPartnerAuthorizationException::class, $unknown);
        $this->assertTrue($unknown->isClientUnknown());
        $this->assertFalse($unknown->isUnauthorized());

        $this->fakeGalaxy('partnerScopeDenied');
        $denied = $this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID, self::ACCOUNT));
        $this->assertInstanceOf(GalaxyPartnerAuthorizationException::class, $denied);
        $this->assertFalse($denied->isClientUnknown());
        $this->assertSame(['smileys:read'], $denied->missingScopes());
        $this->assertFalse($denied->isUnauthorized());

        Cache::flush();
        $this->fakeGalaxy('partnerTokenUserOnly');
        $tokenUser = $this->catchGalaxy(fn () => (new GalaxyApiClient())->debitSmileys(self::UUID, 10, 'CART-1'));
        $this->assertInstanceOf(GalaxyPartnerAuthorizationException::class, $tokenUser);
        $this->assertSame(GalaxyApiException::PARTNER_SCOPE_DENIED, $tokenUser->getErrorCode());
    }

    public function testAccountScopingErrorsAreTyped()
    {
        $this->fakeGalaxy('accountAmbiguous');
        $ambiguous = $this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID));
        $this->assertInstanceOf(GalaxyAccountAmbiguousException::class, $ambiguous);
        $this->assertSame(2, $ambiguous->accountCount());

        Cache::flush();
        $this->fakeGalaxy('accountAmbiguous');
        $debit = $this->catchGalaxy(fn () => (new GalaxyApiClient())->debitSmileys(self::UUID, 10, 'CART-1'));
        $this->assertTrue($debit->isAccountAmbiguous());

        Cache::flush();
        $this->fakeGalaxy('accountNotMember');
        $notMember = $this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID, '001G500001XXXXXXXX'));
        $this->assertTrue($notMember->isAccountNotMember());
        $this->assertFalse($notMember->isUnauthorized());
    }

    public function testUserAndAvailabilityErrors()
    {
        $this->fakeGalaxy('userNotFound');
        $this->assertTrue($this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID))->isNotFound());

        Cache::flush();
        $this->fakeGalaxy('userInactive');
        $inactive = $this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID));
        $this->assertTrue($inactive->isUserInactive());
        $this->assertFalse($inactive->isUnauthorized());

        $this->fakeGalaxy('unauthenticated');
        $this->assertTrue($this->catchGalaxy(fn () => (new GalaxyApiClient())->me('expired'))->isUnauthorized());

        Cache::flush();
        $this->fakeGalaxy('salesforceUnavailable');
        $unavailable = $this->catchGalaxy(fn () => (new GalaxyApiClient())->smileyBalance(self::UUID));
        $this->assertSame(503, $unavailable->getStatusCode());
        $this->assertFalse($unavailable->isUnauthorized());
    }

    public function smileysRouteCases(): array
    {
        return [
            'balance' => ['balance', 200, ['success' => 1, 'result' => 750, 'salesforceAccountId' => self::ACCOUNT]],
            'ambiguous' => ['accountAmbiguous', 200, ['success' => 0, 'code' => 'GALAXY_ACCOUNT_AMBIGUOUS', 'accounts' => 2]],
            'not member' => ['accountNotMember', 200, ['success' => 0, 'code' => 'GALAXY_ACCOUNT_NOT_MEMBER']],
            'user not found' => ['userNotFound', 200, ['success' => 0, 'code' => 'GALAXY_USER_NOT_FOUND']],
            'user inactive' => ['userInactive', 200, ['success' => 0, 'code' => 'GALAXY_USER_INACTIVE']],
            'client unknown' => ['partnerClientUnknown', 502, ['success' => 0, 'code' => 'GALAXY_PARTNER_CLIENT_UNKNOWN']],
            'scope denied' => ['partnerScopeDenied', 502, ['success' => 0, 'code' => 'GALAXY_PARTNER_SCOPE_DENIED']],
            'token user only' => ['partnerTokenUserOnly', 502, ['success' => 0, 'code' => 'GALAXY_PARTNER_SCOPE_DENIED']],
            'salesforce down' => ['salesforceUnavailable', 502, ['code' => 502]],
        ];
    }

    /**
     * @dataProvider smileysRouteCases
     */
    public function testGetSmileysRouteAnswersEachGalaxyResponse(string $fixture, int $status, array $json)
    {
        $this->fakeGalaxy($fixture);

        $this->get('/api/middleware/getSmileys/' . self::UUID . '?salesforceAccountId=' . self::ACCOUNT);

        $this->seeStatusCode($status);
        $this->seeJson($json);
    }

    public function loginRouteCases(): array
    {
        return [
            'client unknown' => ['partnerClientUnknown', 502, 'GALAXY_PARTNER_CLIENT_UNKNOWN'],
            'scope denied' => ['partnerScopeDenied', 502, 'GALAXY_PARTNER_SCOPE_DENIED'],
            'user inactive' => ['userInactive', 403, 'GALAXY_USER_INACTIVE'],
            'user not found' => ['userNotFound', 401, 'GALAXY_INVALID_CREDENTIALS'],
            'token rejected' => ['unauthenticated', 401, 'GALAXY_INVALID_CREDENTIALS'],
        ];
    }

    /**
     * @dataProvider loginRouteCases
     */
    public function testReverseLoginRouteAnswersEachGalaxyRefusal(string $fixture, int $status, string $code)
    {
        $this->fakeGalaxy($fixture);

        $this->post('/api/reverse-login-sso', ['email' => 'dr.martin@example.test', 'password' => 'secret']);

        $this->seeStatusCode($status);
        $this->seeJson(['success' => 0, 'code' => $code]);
    }
}
