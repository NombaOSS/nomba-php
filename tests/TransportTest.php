<?php

declare(strict_types=1);

namespace Nomba\Tests;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nomba\Config;
use Nomba\Exception\ApiException;
use Nomba\Exception\AuthenticationException;
use Nomba\Exception\ConnectionException;
use Nomba\Exception\NombaException;
use Nomba\Exception\NotFoundException;
use Nomba\Exception\PermissionDeniedException;
use Nomba\Exception\RateLimitException;
use Nomba\Exception\ServerException;
use Nomba\Exception\TimeoutException;
use Nomba\Exception\ValidationException;
use Nomba\Http\Transport;
use Nomba\Nomba;

final class TransportTest extends TestCase
{
    private static function connectError(int $errno = 7, string $message = 'cURL error 7: Failed to connect'): TransferException
    {
        return self::networkError($errno, $message, 'GET', 'https://sandbox.nomba.com/x?secret=1');
    }

    public function testSendsStandardHeaders(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([])]);
        $nomba->accounts->getParent();

        $request = $this->request(1);
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(self::ACCOUNT_ID, $request->getHeaderLine('accountId'));
        self::assertSame('nomba-php/' . Nomba::VERSION . ' PHP/' . \PHP_VERSION, $request->getHeaderLine('User-Agent'));
        self::assertSame('', (string) $request->getBody());

        $options = $this->history[1]['options'];
        self::assertSame(30.0, $options['timeout']);
        self::assertFalse($options['allow_redirects']);
        self::assertFalse($options['http_errors']);
    }

    public function testCustomBaseUrlAndTimeout(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([])], ['baseUrl' => 'http://localhost:4010/', 'timeout' => 5]);
        $nomba->accounts->getParent();

        self::assertSame('http://localhost:4010/v1/accounts/parent', (string) $this->request(1)->getUri());
        self::assertSame(5.0, $this->history[1]['options']['timeout']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function successCodes(): iterable
    {
        yield '00' => ['00'];
        yield '200' => ['200'];
        yield '201' => ['201'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('successCodes')]
    public function testSuccessEnvelopeReturnsDataOnly(string $code): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['accountId' => 'a1', 'status' => 'PENDING_BILLING'], $code)]);

        self::assertSame(['accountId' => 'a1', 'status' => 'PENDING_BILLING'], $nomba->accounts->getParent());
    }

    public function testEnvelopeWithoutCodeIsSuccessAndNumericCodeIsNormalised(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::json(200, ['data' => ['x' => 1]]),
            self::json(201, ['code' => 200, 'data' => ['y' => 2]]),
            self::json(200, ['code' => '00']),
        ]);

        self::assertSame(['x' => 1], $nomba->accounts->getParent());
        self::assertSame(['y' => 2], $nomba->accounts->getParent());
        self::assertSame([], $nomba->accounts->getParent(), 'absent data becomes []');
    }

    public function testDataStatusIsNeverAnError(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['id' => 't', 'status' => 'REFUND'])]);

        self::assertSame('REFUND', $nomba->transactions->requery('session-1')['status']);
    }

    public function testNonSuccessCodeOn2xxIsApiException(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(200, ['code' => '99', 'description' => 'Insufficient balance', 'data' => null])]);

        try {
            $nomba->transfers->toBank(['amount' => 1, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b']);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(ApiException::class, $e::class);
            self::assertSame(200, $e->httpStatus);
            self::assertSame('99', $e->nombaCode);
            self::assertSame('Insufficient balance', $e->description);
            self::assertSame('POST', $e->method);
            self::assertSame('/v2/transfers/bank', $e->path);
            self::assertSame('Nomba API error 200 (code "99"): Insufficient balance [POST /v2/transfers/bank]', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statusClasses(): iterable
    {
        yield '400' => [400, ValidationException::class];
        yield '422' => [422, ValidationException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '403' => [403, PermissionDeniedException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ApiException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
        yield '503' => [503, ServerException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statusClasses')]
    public function testHttpStatusMapsToExceptionClass(int $status, string $class): void
    {
        $responses = [self::tokenResponse(), self::json($status, ['code' => (string) $status, 'description' => 'Something failed'])];
        if ($status === 401) {
            $responses[] = self::tokenResponse();
            $responses[] = self::json($status, ['code' => '401', 'description' => 'Something failed']);
        }
        $nomba = $this->nomba($responses, ['maxRetries' => 0]);

        try {
            $nomba->accounts->getSubAccount(['accountRef' => 'ref-1']);
            self::fail('Expected ' . $class);
        } catch (ApiException $e) {
            self::assertSame($class, $e::class);
            self::assertInstanceOf(NombaException::class, $e);
            self::assertSame($status, $e->httpStatus);
            self::assertSame($status, $e->getCode());
            self::assertSame((string) $status, $e->nombaCode);
            self::assertSame(['code' => (string) $status, 'description' => 'Something failed'], $e->body);
            self::assertSame(
                \sprintf('Nomba API error %d (code "%d"): Something failed [GET /v1/accounts/sub-account-details]', $status, $status),
                $e->getMessage(),
                'no query string in the message',
            );
        }
    }

    public function testErrorWithoutJsonUsesReasonPhrase(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), new Response(502, [], '<html>Bad gateway</html>')], ['maxRetries' => 0]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertNull($e->nombaCode);
            self::assertSame('<html>Bad gateway</html>', $e->body);
            self::assertSame('Nomba API error 502: Bad Gateway [GET /v1/accounts/parent]', $e->getMessage());
        }
    }

    public function testErrorUsesMessageFieldWhenNoDescription(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(403, ['message' => 'Forbidden here'])]);

        $this->expectException(PermissionDeniedException::class);
        $this->expectExceptionMessage('Nomba API error 403: Forbidden here [GET /v1/accounts/parent]');
        $nomba->accounts->getParent();
    }

    public function testInvalidJsonOnSuccessIsApiException(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), new Response(200, [], 'not json')]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('response was not valid JSON');
        $nomba->accounts->getParent();
    }

    public function testEmptySuccessBodyReturnsNull(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), new Response(200, [], '')]);

        self::assertSame('', $nomba->bills->lookupBettingCustomer(['providerId' => 'p', 'customerId' => 'c']));
    }

    public function testRateLimitExposesHeaders(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::json(429, ['code' => '429', 'description' => 'Too many requests'], [
                'Retry-After' => '3',
                'X-Rate-Limit-Limit' => '15',
                'X-Rate-Limit-Remaining' => '0',
                'X-Rate-Limit-Window' => '1s',
            ]),
        ]);

        try {
            $nomba->transfers->lookupAccount(['accountNumber' => '1', 'bankCode' => '2']);
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(3.0, $e->retryAfter);
            self::assertSame('15', $e->rateLimitLimit);
            self::assertSame('0', $e->rateLimitRemaining);
            self::assertSame('1s', $e->rateLimitWindow);
            self::assertSame(2, $this->requestCount(), 'POST is not retried on 429');
        }
    }

    public function testRateLimitWithoutHeaders(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(429, ['code' => '429']), new Response(429, ['Retry-After' => 'soon'])], ['maxRetries' => 0]);

        try {
            $nomba->accounts->getParent();
        } catch (RateLimitException $e) {
            self::assertNull($e->retryAfter);
            self::assertNull($e->rateLimitLimit);
            self::assertSame('Nomba API error 429 (code "429"): Too Many Requests [GET /v1/accounts/parent]', $e->getMessage());
        }
        try {
            $nomba->accounts->getParent();
        } catch (RateLimitException $e) {
            self::assertNull($e->retryAfter, 'unparseable Retry-After');
        }
    }

    public function testGetIsRetriedOnServerErrorsWithBackoff(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(500, []), self::json(503, []), self::ok(['ok' => true])]);

        self::assertSame(['ok' => true], $nomba->accounts->getParent());
        self::assertSame(4, $this->requestCount());
        self::assertCount(2, $this->sleeps);
        self::assertGreaterThanOrEqual(0.375, $this->sleeps[0]);
        self::assertLessThanOrEqual(0.5, $this->sleeps[0]);
        self::assertGreaterThanOrEqual(0.75, $this->sleeps[1]);
        self::assertLessThanOrEqual(1.0, $this->sleeps[1]);
    }

    public function testGetGivesUpAfterMaxRetries(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(500, []), self::json(500, []), self::json(500, [])]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected ServerException');
        } catch (ServerException) {
            self::assertSame(4, $this->requestCount(), '1 token + 1 attempt + 2 retries');
            self::assertCount(2, $this->sleeps);
        }
    }

    public function testGetHonoursRetryAfterOn429(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::json(429, [], ['Retry-After' => '2']),
            self::json(429, [], ['Retry-After' => '600']),
            self::ok([]),
        ]);

        $nomba->accounts->getParent();

        self::assertSame([2.0, 60.0], $this->sleeps, 'Retry-After is honoured and capped at 60 s');
    }

    public function testRetryAfterHttpDate(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(429, [], ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 5)]), self::ok([])]);

        $nomba->accounts->getParent();

        self::assertCount(1, $this->sleeps);
        self::assertGreaterThanOrEqual(3.0, $this->sleeps[0]);
        self::assertLessThanOrEqual(5.0, $this->sleeps[0]);
    }

    public function testGetIsRetriedOnConnectionErrorsAndTimeouts(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::connectError(),
            self::connectError(28, 'cURL error 28: Operation timed out'),
            self::ok(['ok' => 1]),
        ]);

        self::assertSame(['ok' => 1], $nomba->accounts->getParent());
        self::assertCount(2, $this->sleeps);
    }

    /**
     * @return iterable<string, array{\Closure(Nomba): mixed}>
     */
    public static function writes(): iterable
    {
        yield 'POST transfer' => [static fn(Nomba $n): mixed => $n->transfers->toBank(['amount' => 1, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b'])];
        yield 'PUT virtual account' => [static fn(Nomba $n): mixed => $n->virtualAccounts->update('ref', ['accountName' => 'x'])];
        yield 'DELETE virtual account' => [static fn(Nomba $n): mixed => $n->virtualAccounts->expire('ref')];
    }

    /**
     * @param \Closure(Nomba): mixed $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('writes')]
    public function testWritesAreNeverRetried(\Closure $call): void
    {
        foreach ([self::json(500, []), self::json(429, [], ['Retry-After' => '0']), self::connectError()] as $failure) {
            $nomba = $this->nomba([self::tokenResponse(), $failure, self::ok([])]);
            try {
                $call($nomba);
                self::fail('Expected exception');
            } catch (ServerException|RateLimitException|ConnectionException) {
                self::assertSame(2, $this->requestCount());
                self::assertSame([], $this->sleeps);
            }
        }
    }

    public function testTimeoutException(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::connectError(28, 'cURL error 28: Operation timed out after 30001 ms')], ['maxRetries' => 0]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected TimeoutException');
        } catch (TimeoutException $e) {
            self::assertInstanceOf(ConnectionException::class, $e);
            self::assertSame('GET', $e->method);
            self::assertSame('/v1/accounts/parent', $e->path);
            self::assertSame('Request to Nomba timed out after 30 s [GET /v1/accounts/parent]', $e->getMessage());
            self::assertInstanceOf(TransferException::class, $e->getPrevious());
        }
    }

    public function testConnectionExceptionHidesUrl(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::connectError()], ['maxRetries' => 0]);

        try {
            $nomba->checkout->listSavedCards(['customerEmail' => 'person@example.com']);
            self::fail('Expected ConnectionException');
        } catch (ConnectionException $e) {
            self::assertSame(ConnectionException::class, $e::class);
            self::assertSame('Could not connect to Nomba [GET /v1/checkout/tokenized-card-data]', $e->getMessage());
        }
    }

    public function testOtherTransferErrorsAreConnectionExceptions(): void
    {
        $request = new Request('GET', 'https://sandbox.nomba.com/');
        $nomba = $this->nomba([
            self::tokenResponse(),
            new RequestException('TLS failure', $request),
            self::transferError('weird'),
            RequestException::create($request, new Response(404, [], '{"code":"404","description":"nope"}')),
        ], ['maxRetries' => 0]);

        foreach ([ConnectionException::class, ConnectionException::class, NotFoundException::class] as $expected) {
            try {
                $nomba->accounts->getParent();
                self::fail('Expected ' . $expected);
            } catch (NombaException $e) {
                self::assertSame($expected, $e::class);
            }
        }
    }

    public function testQueryEncoding(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([]), self::ok([])]);

        $nomba->globalPayout->listBankProviders(['isMobileMoney' => false, 'countryIsoCode' => null]);
        $nomba->checkout->listSavedCards(['customerEmail' => 'a+b@example.com', 'page' => 2, 'limit' => null]);

        self::assertSame('isMobileMoney=false', $this->request(1)->getUri()->getQuery());
        self::assertSame('customerEmail=a%2Bb%40example.com&page=2', $this->request(2)->getUri()->getQuery());
    }

    public function testBuildQueryRejectsNestedValues(): void
    {
        self::assertSame('a=true&b=1.5', Transport::buildQuery(['a' => true, 'b' => 1.5, 'c' => null]));

        $this->expectException(ValidationException::class);
        Transport::buildQuery(['a' => ['x']]);
    }

    public function testBackoffIsExponentialAndCapped(): void
    {
        $transport = new Transport(Config::fromOptions(['clientId' => 'a', 'clientSecret' => 'b', 'accountId' => 'c']));

        foreach ([0 => 0.5, 1 => 1.0, 2 => 2.0, 3 => 4.0, 4 => 8.0, 5 => 8.0, 9 => 8.0] as $attempt => $base) {
            $delay = $transport->backoff($attempt);
            self::assertGreaterThanOrEqual($base * 0.75, $delay);
            self::assertLessThanOrEqual($base, $delay);
        }
    }

    public function testDefaultSleeperSleeps(): void
    {
        $config = Config::fromOptions(['clientId' => 'a', 'clientSecret' => 'b', 'accountId' => 'c']);
        $start = microtime(true);
        ($config->sleeper)(0.01);
        ($config->sleeper)(0.0);

        self::assertGreaterThanOrEqual(0.009, microtime(true) - $start);
    }
}
