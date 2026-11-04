<?php

declare(strict_types=1);

namespace Nomba\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\NetworkException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nomba\Nomba;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public const CLIENT_ID = 'test-client-id';
    public const CLIENT_SECRET = 'test-client-secret-DO-NOT-LEAK';
    public const ACCOUNT_ID = '00000000-0000-4000-8000-000000000001';
    public const ACCESS_TOKEN = 'test-access-token-1';
    public const REFRESH_TOKEN = 'test-refresh-token-1';

    protected MockHandler $mock;

    /** @var list<array{request: RequestInterface, options: array<mixed>}> */
    protected array $history = [];

    /** @var list<float> */
    protected array $sleeps = [];

    /**
     * @param list<ResponseInterface|\Throwable> $responses
     * @param array<string, mixed>               $options
     */
    protected function nomba(array $responses = [], array $options = []): Nomba
    {
        $this->mock = new MockHandler($responses);
        $this->history = [];
        $this->sleeps = [];
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::tap(function (RequestInterface $request, array $options): void {
            $this->history[] = ['request' => $request, 'options' => $options];
        }));

        /** @var array{clientId?: string} $merged */
        $merged = $options + [
            'clientId' => self::CLIENT_ID,
            'clientSecret' => self::CLIENT_SECRET,
            'accountId' => self::ACCOUNT_ID,
            'httpClient' => new Client(['handler' => $stack]),
            'sleeper' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ];

        return new Nomba($merged);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    protected static function json(int $status, mixed $body, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, \JSON_THROW_ON_ERROR));
    }

    protected static function ok(mixed $data, string $code = '00'): Response
    {
        return self::json(200, ['code' => $code, 'description' => 'Success', 'data' => $data]);
    }

    protected static function tokenResponse(
        string $access = self::ACCESS_TOKEN,
        string $refresh = self::REFRESH_TOKEN,
        int $expiresIn = 1800,
    ): Response {
        return self::ok([
            'businessId' => 'biz-1',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', time() + $expiresIn),
        ]);
    }

    protected function request(int $index): RequestInterface
    {
        self::assertArrayHasKey($index, $this->history, "No request #{$index} was sent");

        return $this->history[$index]['request'];
    }

    /**
     * @return array<mixed>|null
     */
    protected function body(int $index): ?array
    {
        $raw = (string) $this->request($index)->getBody();
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    protected function query(int $index): array
    {
        parse_str($this->request($index)->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    protected function requestCount(): int
    {
        return \count($this->history);
    }

    /**
     * A failure with no response, as Guzzle reports it: cURL's errno in the
     * handler context on Guzzle 7, a timeout class on Guzzle 8. The Guzzle 7
     * constructor is called through reflection because static analysis runs
     * against Guzzle 8.
     */
    protected static function networkError(int $errno, string $message, string $method = 'GET', string $url = 'https://sandbox.nomba.com/'): TransferException
    {
        $request = new Request($method, $url);
        if (!class_exists(NetworkException::class)) {
            return (new \ReflectionClass(ConnectException::class))->newInstance($message, $request, null, ['errno' => $errno]);
        }

        return $errno === 28 ? new NetworkTimeoutException($message, $request) : new ConnectException($message, $request);
    }

    /**
     * A bare TransferException; Guzzle 8 requires the request, Guzzle 7 doesn't accept it.
     */
    protected static function transferError(string $message): TransferException
    {
        $args = class_exists(NetworkException::class) ? [$message, new Request('GET', 'https://sandbox.nomba.com/')] : [$message];

        return (new \ReflectionClass(TransferException::class))->newInstanceArgs($args);
    }
}
