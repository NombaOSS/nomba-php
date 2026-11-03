<?php

declare(strict_types=1);

namespace Nomba\Http;

/**
 * What a request is, which decides its retry policy and error-body handling.
 *
 * @internal
 */
enum RequestKind
{
    /** An API call. Retried only when it is a GET. */
    case Api;

    /** Token issue or refresh. Retried; `data` is stripped from error bodies. */
    case Token;

    /** Token revoke. Never retried; `data` is stripped from error bodies. */
    case TokenRevoke;

    public function retries(string $method): bool
    {
        return match ($this) {
            self::Api => $method === 'GET',
            self::Token => true,
            self::TokenRevoke => false,
        };
    }

    public function isTokenEndpoint(): bool
    {
        return $this !== self::Api;
    }
}
