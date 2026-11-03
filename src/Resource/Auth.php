<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;

/**
 * Token management. Tokens are obtained and refreshed automatically; you only
 * need this to revoke the current token (for example on credential rotation).
 */
final class Auth extends AbstractResource
{
    /**
     * Revoke the cached access token (POST /v1/auth/token/revoke) and clear the
     * token store. Does nothing if no token has been obtained yet.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function revoke(): void
    {
        $this->client->tokens->revoke();
    }
}
