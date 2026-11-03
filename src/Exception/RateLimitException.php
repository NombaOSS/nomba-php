<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * HTTP 429. `retryAfter` is the server's Retry-After in seconds when sent; the
 * X-Rate-Limit-* header values are passed through as strings.
 */
final class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        ?int $httpStatus = null,
        ?string $nombaCode = null,
        ?string $description = null,
        mixed $body = null,
        ?string $method = null,
        ?string $path = null,
        public readonly ?float $retryAfter = null,
        public readonly ?string $rateLimitLimit = null,
        public readonly ?string $rateLimitRemaining = null,
        public readonly ?string $rateLimitWindow = null,
    ) {
        parent::__construct($message, $httpStatus, $nombaCode, $description, $body, $method, $path);
    }
}
