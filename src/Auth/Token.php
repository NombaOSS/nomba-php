<?php

declare(strict_types=1);

namespace Nomba\Auth;

/**
 * An access token pair. Immutable. Its debug output redacts both tokens.
 */
final class Token
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $accessToken,
        #[\SensitiveParameter]
        public readonly string $refreshToken,
        public readonly \DateTimeImmutable $expiresAt,
    ) {}

    /**
     * Seconds until expiry (negative once expired).
     */
    public function secondsUntilExpiry(?int $now = null): int
    {
        return $this->expiresAt->getTimestamp() - ($now ?? time());
    }

    /**
     * For storing the token in a shared cache. Treat the result as a secret.
     *
     * @return array{accessToken: string, refreshToken: string, expiresAt: string}
     */
    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresAt' => $this->expiresAt->format(\DateTimeInterface::RFC3339),
        ];
    }

    /**
     * @param array{accessToken: string, refreshToken: string, expiresAt: string} $data
     */
    public static function fromArray(#[\SensitiveParameter] array $data): self
    {
        return new self($data['accessToken'], $data['refreshToken'], new \DateTimeImmutable($data['expiresAt']));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'accessToken' => '[redacted]',
            'refreshToken' => '[redacted]',
            'expiresAt' => $this->expiresAt->format(\DateTimeInterface::RFC3339),
        ];
    }
}
