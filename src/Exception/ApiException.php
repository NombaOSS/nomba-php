<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * An error response from the Nomba API, or a 2xx response whose envelope
 * `code` is not a success code. Parent of the HTTP-status specific classes.
 *
 * Nomba's envelope `code` is exposed as `$nombaCode`, because PHP reserves
 * `Exception::$code` (it holds the HTTP status here).
 */
class ApiException extends NombaException
{
    /**
     * @param mixed $body the parsed response body (or the raw string if it was not JSON)
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $nombaCode = null,
        public readonly ?string $description = null,
        public readonly mixed $body = null,
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
