<?php

declare(strict_types=1);

namespace Nomba\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Nomba\Config;
use Nomba\Exception\ApiException;
use Nomba\Exception\AuthenticationException;
use Nomba\Exception\ConnectionException;
use Nomba\Exception\NotFoundException;
use Nomba\Exception\PermissionDeniedException;
use Nomba\Exception\RateLimitException;
use Nomba\Exception\ServerException;
use Nomba\Exception\TimeoutException;
use Nomba\Exception\ValidationException;
use Nomba\Nomba;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends one logical request: builds the URL and headers, retries when allowed,
 * unwraps the Nomba envelope and maps failures to exceptions.
 *
 * @internal
 */
final class Transport
{
    public const SUCCESS_CODES = ['00', '200', '201'];

    private const MAX_BACKOFF_SECONDS = 8.0;
    private const MAX_RETRY_AFTER_SECONDS = 60.0;
    private const CURLE_OPERATION_TIMEDOUT = 28;

    private readonly ClientInterface $http;

    public function __construct(private readonly Config $config)
    {
        $this->http = $config->httpClient ?? new Client();
    }

    /**
     * @param array<string, mixed>  $query   null values are dropped, booleans sent as true/false
     * @param array<mixed>|null     $body    JSON body; null sends no body
     * @param array<string, string> $headers extra headers (for example Authorization)
     * @param bool                  $retry   whether connection errors, timeouts, 429 and 5xx may be retried
     *
     * @return mixed the envelope's `data`
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function request(
        string $method,
        string $path,
        array $query,
        ?array $body,
        #[\SensitiveParameter]
        array $headers,
        bool $retry,
    ): mixed {
        $method = strtoupper($method);
        $url = $this->config->baseUrl . $path;
        $queryString = self::buildQuery($query);
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $allHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'accountId' => $this->config->accountId,
            'User-Agent' => self::userAgent(),
        ] + $headers;

        $payload = null;
        if ($body !== null) {
            $payload = $body === [] ? '{}' : json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
        }

        $maxRetries = $retry ? $this->config->maxRetries : 0;
        for ($attempt = 0; ; ++$attempt) {
            $canRetry = $attempt < $maxRetries;
            try {
                $response = $this->send(new Request($method, $url, $allHeaders, $payload), $method, $path);
            } catch (ConnectionException $e) {
                if ($canRetry) {
                    $this->sleep($this->backoff($attempt));
                    continue;
                }
                throw $e;
            }

            $status = $response->getStatusCode();
            if ($canRetry && ($status === 429 || $status >= 500)) {
                $delay = $status === 429 ? self::retryAfter($response) : null;
                $this->sleep($delay !== null ? min($delay, self::MAX_RETRY_AFTER_SECONDS) : $this->backoff($attempt));
                continue;
            }

            return self::unwrap($response, $method, $path);
        }
    }

    public static function userAgent(): string
    {
        return 'nomba-php/' . Nomba::VERSION . ' PHP/' . \PHP_VERSION;
    }

    /**
     * Delay before retry number $attempt + 1: 0.5 s, 1 s, 2 s, ... capped at 8 s,
     * minus up to 25% random jitter.
     */
    public function backoff(int $attempt): float
    {
        $base = min(self::MAX_BACKOFF_SECONDS, 0.5 * (2 ** $attempt));
        $jitter = random_int(0, 250) / 1000;

        return $base * (1 - $jitter);
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function buildQuery(array $query): string
    {
        $clean = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (\is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (\is_int($value) || \is_float($value)) {
                $value = (string) $value;
            } elseif (!\is_string($value)) {
                throw new ValidationException(\sprintf('Query parameter %s must be a string, number or boolean.', $key));
            }
            $clean[$key] = $value;
        }

        return http_build_query($clean, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * @throws ConnectionException
     */
    private function send(Request $request, string $method, string $path): ResponseInterface
    {
        try {
            return $this->http->send($request, [
                RequestOptions::TIMEOUT => $this->config->timeout,
                RequestOptions::CONNECT_TIMEOUT => min(10.0, $this->config->timeout),
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::ALLOW_REDIRECTS => false,
            ]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                return $response;
            }
            throw $this->connectionError($e, $method, $path);
        } catch (GuzzleException $e) {
            throw $this->connectionError($e, $method, $path);
        }
    }

    /**
     * The Guzzle message is kept only as the previous exception, because it
     * contains the full URL including the query string.
     */
    private function connectionError(GuzzleException $e, string $method, string $path): ConnectionException
    {
        $context = $e instanceof ConnectException || $e instanceof RequestException ? $e->getHandlerContext() : [];
        $timedOut = ($context['errno'] ?? null) === self::CURLE_OPERATION_TIMEDOUT
            || stripos($e->getMessage(), 'timed out') !== false;

        if ($timedOut) {
            return new TimeoutException(
                \sprintf('Request to Nomba timed out after %s s [%s %s]', $this->config->timeout, $method, $path),
                $method,
                $path,
                $e,
            );
        }

        return new ConnectionException(
            \sprintf('Could not connect to Nomba [%s %s]', $method, $path),
            $method,
            $path,
            $e,
        );
    }

    private function sleep(float $seconds): void
    {
        ($this->config->sleeper)($seconds);
    }

    /**
     * @throws ApiException
     */
    private static function unwrap(ResponseInterface $response, string $method, string $path): mixed
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $isJson = true;
        $body = null;
        if (trim($raw) !== '') {
            try {
                $body = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
            } catch (\JsonException) {
                $isJson = false;
                $body = $raw;
            }
        }

        $code = null;
        $description = null;
        if (\is_array($body)) {
            $code = isset($body['code']) && \is_scalar($body['code']) ? (string) $body['code'] : null;
            foreach (['description', 'message'] as $key) {
                if (isset($body[$key]) && \is_string($body[$key]) && $body[$key] !== '') {
                    $description = $body[$key];
                    break;
                }
            }
        }

        if ($status >= 200 && $status < 300) {
            if (!$isJson) {
                throw new ApiException(
                    self::message($status, null, 'response was not valid JSON', $method, $path),
                    $status,
                    null,
                    'response was not valid JSON',
                    $body,
                    $method,
                    $path,
                );
            }
            if ($code === null || \in_array($code, self::SUCCESS_CODES, true)) {
                return \is_array($body) && \array_key_exists('data', $body) ? $body['data'] : null;
            }
            $description ??= 'request was not successful';

            throw new ApiException(self::message($status, $code, $description, $method, $path), $status, $code, $description, $body, $method, $path);
        }

        $description ??= $response->getReasonPhrase() !== '' ? $response->getReasonPhrase() : 'request failed';
        $message = self::message($status, $code, $description, $method, $path);

        if ($status === 429) {
            throw new RateLimitException(
                $message,
                $status,
                $code,
                $description,
                $body,
                $method,
                $path,
                self::retryAfter($response),
                self::header($response, 'X-Rate-Limit-Limit'),
                self::header($response, 'X-Rate-Limit-Remaining'),
                self::header($response, 'X-Rate-Limit-Window'),
            );
        }

        $class = match (true) {
            $status === 400, $status === 422 => ValidationException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionDeniedException::class,
            $status === 404 => NotFoundException::class,
            $status >= 500 => ServerException::class,
            default => ApiException::class,
        };

        throw new $class($message, $status, $code, $description, $body, $method, $path);
    }

    private static function message(int $status, ?string $code, string $description, string $method, string $path): string
    {
        $codePart = $code === null ? '' : \sprintf(' (code "%s")', $code);

        return \sprintf('Nomba API error %d%s: %s [%s %s]', $status, $codePart, $description, $method, $path);
    }

    private static function header(ResponseInterface $response, string $name): ?string
    {
        $value = $response->getHeaderLine($name);

        return $value === '' ? null : $value;
    }

    /**
     * Retry-After in seconds (numeric or HTTP-date form), or null if absent/invalid.
     */
    private static function retryAfter(ResponseInterface $response): ?float
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }
        $time = strtotime($value);

        return $time === false ? null : (float) max(0, $time - time());
    }
}
