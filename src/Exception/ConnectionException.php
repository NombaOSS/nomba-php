<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * The request did not get an HTTP response (DNS, TLS, connection reset, ...).
 *
 * For POST/PUT/DELETE calls the request may or may not have reached Nomba.
 * Requery with your merchantTxRef before trying again.
 */
class ConnectionException extends NombaException
{
    public function __construct(
        string $message,
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
