<?php

declare(strict_types=1);

namespace Nomba\Auth;

use Nomba\Config;
use Nomba\Exception\ApiException;
use Nomba\Exception\AuthenticationException;
use Nomba\Exception\ConnectionException;
use Nomba\Http\Transport;

/**
 * Obtains, caches, refreshes and revokes access tokens.
 *
 * PHP runs one request at a time per process, so there is no concurrent token
 * request to share; a shared {@see TokenStore} lets several processes reuse one token.
 *
 * @internal
 */
final class TokenManager
{
    /** Refresh when the token is within this many seconds of expiry. */
    public const REFRESH_WINDOW_SECONDS = 300;

    /** Used when the token response has no parseable expiresAt. */
    private const FALLBACK_LIFETIME_SECONDS = 1500;

    public function __construct(
        private readonly Config $config,
        private readonly Transport $transport,
    ) {}

    /**
     * @throws ApiException
     * @throws ConnectionException
     */
    public function accessToken(): string
    {
        $token = $this->config->tokenStore->get();
        if ($token !== null && $token->secondsUntilExpiry() > self::REFRESH_WINDOW_SECONDS) {
            return $token->accessToken;
        }

        $fresh = $token !== null && $token->refreshToken !== '' ? $this->refresh($token) : $this->issue();
        $this->config->tokenStore->set($fresh);

        return $fresh->accessToken;
    }

    /**
     * Forget the cached token, but only if it is the one the API rejected
     * (another process may already have stored a newer one).
     */
    public function invalidate(#[\SensitiveParameter] string $rejected): void
    {
        $token = $this->config->tokenStore->get();
        if ($token !== null && hash_equals($token->accessToken, $rejected)) {
            $this->config->tokenStore->set(null);
        }
    }

    /**
     * Revoke the cached access token (if any) and clear the store.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function revoke(): void
    {
        $token = $this->config->tokenStore->get();
        if ($token === null) {
            return;
        }

        try {
            $this->transport->request(
                'POST',
                '/v1/auth/token/revoke',
                [],
                ['clientId' => $this->config->clientId, 'access_token' => $token->accessToken],
                ['Authorization' => 'Bearer ' . $token->accessToken],
                false,
            );
        } finally {
            $this->config->tokenStore->set(null);
        }
    }

    /**
     * @throws ApiException
     * @throws ConnectionException
     */
    private function issue(): Token
    {
        return $this->tokenRequest('/v1/auth/token/issue', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
        ]);
    }

    /**
     * Refresh; on a 4xx fall back to issuing a new token. On other failures
     * (5xx, network) keep using the current token while it is still valid.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    private function refresh(Token $current): Token
    {
        try {
            return $this->tokenRequest('/v1/auth/token/refresh', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $current->refreshToken,
            ]);
        } catch (AuthenticationException $e) {
            return $this->issue();
        } catch (ApiException|ConnectionException $e) {
            if ($current->secondsUntilExpiry() > 0) {
                return $current;
            }
            throw $e;
        }
    }

    /**
     * @param array<string, string> $body
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    private function tokenRequest(string $path, #[\SensitiveParameter] array $body): Token
    {
        try {
            $data = $this->transport->request('POST', $path, [], $body, [], true);
        } catch (ApiException $e) {
            $status = $e->httpStatus ?? 0;
            $isClientError = ($status >= 400 && $status < 500 && $status !== 429) || ($status >= 200 && $status < 300);
            if (!$isClientError || $e instanceof AuthenticationException) {
                throw $e;
            }

            throw new AuthenticationException(
                'Could not obtain a Nomba access token: ' . $e->getMessage(),
                $e->httpStatus,
                $e->nombaCode,
                $e->description,
                $e->body,
                $e->method,
                $e->path,
                $e,
            );
        }

        if (!\is_array($data)) {
            $data = [];
        }
        $access = $data['access_token'] ?? null;
        if (!\is_string($access) || $access === '') {
            throw new AuthenticationException(
                \sprintf('Nomba token response did not include an access_token [POST %s]', $path),
                200,
                null,
                'missing access_token',
                null,
                'POST',
                $path,
            );
        }
        $refresh = \is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : '';

        return new Token($access, $refresh, self::expiry($data['expiresAt'] ?? null));
    }

    private static function expiry(mixed $value): \DateTimeImmutable
    {
        if (\is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                // fall through
            }
        }

        return (new \DateTimeImmutable())->modify('+' . self::FALLBACK_LIFETIME_SECONDS . ' seconds');
    }
}
