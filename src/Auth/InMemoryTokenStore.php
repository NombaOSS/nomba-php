<?php

declare(strict_types=1);

namespace Nomba\Auth;

/**
 * Keeps the token in this object for the life of the process.
 */
final class InMemoryTokenStore implements TokenStore
{
    private ?Token $token = null;

    public function get(): ?Token
    {
        return $this->token;
    }

    public function set(?Token $token): void
    {
        $this->token = $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['token' => $this->token === null ? null : '[redacted]'];
    }
}
