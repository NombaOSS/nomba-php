<?php

declare(strict_types=1);

namespace Nomba\Tests;

use GuzzleHttp\Psr7\Response;
use Nomba\Auth\InMemoryTokenStore;
use Nomba\Auth\Token;
use Nomba\Auth\TokenStore;
use Nomba\Exception\AuthenticationException;
use Nomba\Exception\ServerException;
use Nomba\Exception\ValidationException;

final class AuthTest extends TestCase
{
    private static function storeWith(int $expiresIn, string $access = 'old-access', string $refresh = 'old-refresh'): InMemoryTokenStore
    {
        $store = new InMemoryTokenStore();
        $store->set(new Token($access, $refresh, new \DateTimeImmutable('@' . (time() + $expiresIn))));

        return $store;
    }

    public function testFirstCallIssuesTokenThenSendsBearer(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['amount' => '10.00', 'currency' => 'NGN', 'timeCreated' => 'x'])]);

        $balance = $nomba->accounts->getBalance();

        self::assertSame('10.00', $balance['amount']);
        $issue = $this->request(0);
        self::assertSame('POST', $issue->getMethod());
        self::assertSame('https://sandbox.nomba.com/v1/auth/token/issue', (string) $issue->getUri());
        self::assertSame(self::ACCOUNT_ID, $issue->getHeaderLine('accountId'));
        self::assertFalse($issue->hasHeader('Authorization'));
        self::assertSame(
            ['grant_type' => 'client_credentials', 'client_id' => self::CLIENT_ID, 'client_secret' => self::CLIENT_SECRET],
            $this->body(0),
        );
        self::assertSame('Bearer ' . self::ACCESS_TOKEN, $this->request(1)->getHeaderLine('Authorization'));
    }

    public function testTokenIsCachedAcrossCalls(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([]), self::ok([]), self::ok([])]);

        $nomba->accounts->getParent();
        $nomba->accounts->getParent();
        $nomba->transfers->listBanks();

        self::assertSame(4, $this->requestCount());
        self::assertSame('/v1/auth/token/issue', $this->request(0)->getUri()->getPath());
        foreach ([1, 2, 3] as $i) {
            self::assertSame('Bearer ' . self::ACCESS_TOKEN, $this->request($i)->getHeaderLine('Authorization'));
        }
    }

    public function testTokenStoreIsShared(): void
    {
        $store = new InMemoryTokenStore();
        $first = $this->nomba([self::tokenResponse(), self::ok([])], ['tokenStore' => $store]);
        $first->accounts->getParent();

        $second = $this->nomba([self::ok([])], ['tokenStore' => $store]);
        $second->accounts->getParent();

        self::assertSame(1, $this->requestCount());
        self::assertSame('Bearer ' . self::ACCESS_TOKEN, $this->request(0)->getHeaderLine('Authorization'));
    }

    public function testRefreshesWithinFiveMinutesOfExpiry(): void
    {
        $store = self::storeWith(120);
        $nomba = $this->nomba([self::tokenResponse('new-access', 'new-refresh'), self::ok([])], ['tokenStore' => $store]);

        $nomba->accounts->getParent();

        self::assertSame('/v1/auth/token/refresh', $this->request(0)->getUri()->getPath());
        self::assertSame(['grant_type' => 'refresh_token', 'refresh_token' => 'old-refresh'], $this->body(0));
        self::assertSame('Bearer new-access', $this->request(1)->getHeaderLine('Authorization'));
        self::assertSame('new-refresh', $store->get()?->refreshToken);
    }

    public function testValidTokenOutsideRefreshWindowIsUsedAsIs(): void
    {
        $nomba = $this->nomba([self::ok([])], ['tokenStore' => self::storeWith(600)]);

        $nomba->accounts->getParent();

        self::assertSame(1, $this->requestCount());
        self::assertSame('Bearer old-access', $this->request(0)->getHeaderLine('Authorization'));
    }

    public function testRefreshFailureWith4xxFallsBackToIssue(): void
    {
        $nomba = $this->nomba([
            self::json(400, ['code' => '400', 'description' => 'invalid refresh token']),
            self::tokenResponse('issued-access'),
            self::ok([]),
        ], ['tokenStore' => self::storeWith(-10)]);

        $nomba->accounts->getParent();

        self::assertSame('/v1/auth/token/refresh', $this->request(0)->getUri()->getPath());
        self::assertSame('/v1/auth/token/issue', $this->request(1)->getUri()->getPath());
        self::assertSame('Bearer issued-access', $this->request(2)->getHeaderLine('Authorization'));
    }

    public function testRefreshServerErrorKeepsStillValidToken(): void
    {
        $nomba = $this->nomba([
            self::json(503, ['code' => '503', 'description' => 'down']),
            self::json(503, ['code' => '503', 'description' => 'down']),
            self::json(503, ['code' => '503', 'description' => 'down']),
            self::ok([]),
        ], ['tokenStore' => self::storeWith(120)]);

        $nomba->accounts->getParent();

        self::assertSame(4, $this->requestCount(), 'refresh is retried like token issue');
        self::assertSame('Bearer old-access', $this->request(3)->getHeaderLine('Authorization'));
    }

    public function testRefreshServerErrorWithExpiredTokenThrows(): void
    {
        $nomba = $this->nomba([self::json(500, ['code' => '500', 'description' => 'down'])], [
            'tokenStore' => self::storeWith(-10),
            'maxRetries' => 0,
        ]);

        $this->expectException(ServerException::class);
        $nomba->accounts->getParent();
    }

    public function testStoredTokenWithoutRefreshTokenIsReissued(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([])], ['tokenStore' => self::storeWith(-10, 'a', '')]);

        $nomba->accounts->getParent();

        self::assertSame('/v1/auth/token/issue', $this->request(0)->getUri()->getPath());
    }

    public function testUnauthorizedRetriesOnceWithNewTokenEvenForPost(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse('first'),
            self::json(401, ['code' => '401', 'description' => 'Unauthorized']),
            self::tokenResponse('second'),
            self::ok(['id' => 'tx-1', 'status' => 'SUCCESS']),
        ]);

        $result = $nomba->transfers->toBank([
            'amount' => 100,
            'accountNumber' => '0123456789',
            'accountName' => 'Test',
            'bankCode' => '058',
            'merchantTxRef' => 'ref-1',
        ]);

        self::assertSame('tx-1', $result['id'] ?? null);
        self::assertSame(4, $this->requestCount());
        self::assertSame('Bearer first', $this->request(1)->getHeaderLine('Authorization'));
        self::assertSame('/v1/auth/token/issue', $this->request(2)->getUri()->getPath());
        self::assertSame('Bearer second', $this->request(3)->getHeaderLine('Authorization'));
        self::assertSame($this->body(1), $this->body(3), 'the replayed POST is identical, same merchantTxRef');
    }

    public function testSecondUnauthorizedThrows(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse('first'),
            self::json(401, ['code' => '401', 'description' => 'Unauthorized']),
            self::tokenResponse('second'),
            self::json(401, ['code' => '401', 'description' => 'Unauthorized']),
        ]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->httpStatus);
            self::assertSame(4, $this->requestCount());
        }
    }

    public function testInvalidateKeepsNewerTokenFromAnotherProcess(): void
    {
        $store = new class implements TokenStore {
            public int $reads = 0;

            public ?Token $token = null;

            public function get(): ?Token
            {
                ++$this->reads;
                if ($this->reads === 2) {
                    // Another process stored a newer token while our request was in flight.
                    $this->token = new Token('newer', 'r', new \DateTimeImmutable('+30 minutes'));
                }

                return $this->token;
            }

            public function set(?Token $token): void
            {
                $this->token = $token;
            }
        };
        $nomba = $this->nomba([
            self::tokenResponse('first'),
            self::json(401, ['code' => '401', 'description' => 'Unauthorized']),
            self::ok([]),
        ], ['tokenStore' => $store]);

        $nomba->accounts->getParent();

        self::assertSame(3, $this->requestCount());
        self::assertSame('Bearer newer', $this->request(2)->getHeaderLine('Authorization'));
    }

    public function testIssueFailureIsAuthenticationException(): void
    {
        $nomba = $this->nomba([self::json(400, ['code' => '400', 'description' => 'Invalid client credentials'])]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame(400, $e->httpStatus);
            self::assertSame('400', $e->nombaCode);
            self::assertStringContainsString('Could not obtain a Nomba access token', $e->getMessage());
            self::assertStringNotContainsString(self::CLIENT_SECRET, $e->getMessage());
            self::assertInstanceOf(ValidationException::class, $e->getPrevious());
        }
    }

    public function testIssueWithNonSuccessCodeIsAuthenticationException(): void
    {
        $nomba = $this->nomba([self::ok(null, '99')]);

        $this->expectException(AuthenticationException::class);
        $nomba->accounts->getParent();
    }

    public function testIssueServerErrorIsRetriedThenThrown(): void
    {
        $nomba = $this->nomba([
            self::json(502, []),
            self::json(502, []),
            self::json(502, []),
        ]);

        $this->expectException(ServerException::class);
        try {
            $nomba->accounts->getParent();
        } finally {
            self::assertSame(3, $this->requestCount());
        }
    }

    public function testTokenResponseWithoutAccessTokenThrows(): void
    {
        $nomba = $this->nomba([self::ok(['refresh_token' => 'r'])]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('did not include an access_token');
        $nomba->accounts->getParent();
    }

    public function testUnparseableExpiryFallsBackToShortLifetime(): void
    {
        $store = new InMemoryTokenStore();
        $nomba = $this->nomba([self::ok(['access_token' => 'a', 'expiresAt' => 'garbage']), self::ok([])], ['tokenStore' => $store]);

        $nomba->accounts->getParent();

        $token = $store->get();
        self::assertNotNull($token);
        self::assertSame('', $token->refreshToken);
        self::assertGreaterThan(1400, $token->secondsUntilExpiry());
        self::assertLessThanOrEqual(1500, $token->secondsUntilExpiry());
    }

    public function testRevokeSendsTokenAndClearsStore(): void
    {
        $store = self::storeWith(600, 'to-revoke');
        $nomba = $this->nomba([self::json(200, ['code' => '00', 'description' => 'Token revoked successfully'])], ['tokenStore' => $store]);

        $nomba->auth->revoke();

        self::assertSame('/v1/auth/token/revoke', $this->request(0)->getUri()->getPath());
        self::assertSame(['clientId' => self::CLIENT_ID, 'access_token' => 'to-revoke'], $this->body(0));
        self::assertSame('Bearer to-revoke', $this->request(0)->getHeaderLine('Authorization'));
        self::assertNull($store->get());
    }

    public function testRevokeClearsStoreEvenOnFailure(): void
    {
        $store = self::storeWith(600);
        $nomba = $this->nomba([self::json(401, ['code' => '401', 'description' => 'Unauthorized'])], ['tokenStore' => $store]);

        try {
            $nomba->auth->revoke();
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            self::assertNull($store->get());
            self::assertSame(1, $this->requestCount(), 'revoke is not retried with a new token');
        }
    }

    public function testRevokeWithoutTokenIsNoop(): void
    {
        $nomba = $this->nomba([]);

        $nomba->auth->revoke();

        self::assertSame(0, $this->requestCount());
    }

    public function testTokenDebugOutputAndArrayRoundTrip(): void
    {
        $token = new Token('secret-access', 'secret-refresh', new \DateTimeImmutable('2030-01-01T00:00:00Z'));
        $store = new InMemoryTokenStore();
        $store->set($token);

        $dump = print_r($token, true) . print_r($store, true);
        ob_start();
        var_dump($token, $store);
        $dump .= (string) ob_get_clean();
        self::assertStringNotContainsString('secret-access', $dump);
        self::assertStringNotContainsString('secret-refresh', $dump);

        $copy = Token::fromArray($token->toArray());
        self::assertSame('secret-access', $copy->accessToken);
        self::assertSame('secret-refresh', $copy->refreshToken);
        self::assertSame($token->expiresAt->getTimestamp(), $copy->expiresAt->getTimestamp());
        self::assertSame(['token' => null], (new InMemoryTokenStore())->__debugInfo());
    }

    public function testSecretsNeverAppearInExceptions(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), new Response(500, [], 'oops')], ['maxRetries' => 0]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected exception');
        } catch (ServerException $e) {
            $text = $e->getMessage() . print_r($e->body, true);
            self::assertStringNotContainsString(self::ACCESS_TOKEN, $text);
            self::assertStringNotContainsString(self::CLIENT_SECRET, $text);
            self::assertStringNotContainsString('Bearer', $text);
        }
    }
}
