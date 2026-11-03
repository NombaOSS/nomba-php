<?php

declare(strict_types=1);

namespace Nomba\Http;

use Nomba\Auth\TokenManager;
use Nomba\Config;
use Nomba\Exception\ApiException;
use Nomba\Exception\AuthenticationException;
use Nomba\Exception\ConnectionException;

/**
 * Authenticated API requests (RequestKind::Api: only GET is retried automatically); a 401 triggers one
 * token renewal and a single replay (safe for POST: Nomba did not process it).
 *
 * @internal
 */
final class ApiClient
{
    public function __construct(
        public readonly Config $config,
        private readonly Transport $transport,
        public readonly TokenManager $tokens,
    ) {}

    /**
     * @param array<string, mixed> $query
     * @param array<mixed>|null    $body
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): mixed
    {
        $token = $this->tokens->accessToken();

        try {
            return $this->transport->request($method, $path, $query, $body, ['Authorization' => 'Bearer ' . $token]);
        } catch (AuthenticationException $e) {
            if ($e->httpStatus !== 401) {
                throw $e;
            }
            $this->tokens->invalidate($token);
            $token = $this->tokens->accessToken();

            return $this->transport->request($method, $path, $query, $body, ['Authorization' => 'Bearer ' . $token]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['config' => $this->config];
    }
}
