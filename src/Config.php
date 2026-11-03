<?php

declare(strict_types=1);

namespace Nomba;

use GuzzleHttp\ClientInterface;
use Nomba\Auth\InMemoryTokenStore;
use Nomba\Auth\TokenStore;
use Nomba\Exception\ConfigurationException;

/**
 * Resolved client configuration. Built by {@see Nomba}; its debug output
 * redacts the client secret.
 *
 * @phpstan-type NombaOptions array{
 *     clientId?: string,
 *     clientSecret?: string,
 *     accountId?: string,
 *     environment?: 'sandbox'|'production'|string,
 *     baseUrl?: string,
 *     timeout?: int|float,
 *     maxRetries?: int,
 *     tokenStore?: TokenStore,
 *     httpClient?: ClientInterface,
 *     sleeper?: callable(float): void,
 * }
 */
final class Config
{
    public const ENVIRONMENT_SANDBOX = 'sandbox';
    public const ENVIRONMENT_PRODUCTION = 'production';

    public const BASE_URLS = [
        self::ENVIRONMENT_SANDBOX => 'https://sandbox.nomba.com',
        self::ENVIRONMENT_PRODUCTION => 'https://api.nomba.com',
    ];

    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_RETRIES = 2;

    private const KNOWN_OPTIONS = [
        'clientId', 'clientSecret', 'accountId', 'environment', 'baseUrl',
        'timeout', 'maxRetries', 'tokenStore', 'httpClient', 'sleeper',
    ];

    /** @var \Closure(float): void */
    public readonly \Closure $sleeper;

    private function __construct(
        public readonly string $clientId,
        #[\SensitiveParameter]
        public readonly string $clientSecret,
        public readonly string $accountId,
        public readonly string $environment,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly int $maxRetries,
        public readonly TokenStore $tokenStore,
        public readonly ?ClientInterface $httpClient,
        ?\Closure $sleeper,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ConfigurationException
     */
    public static function fromOptions(#[\SensitiveParameter] array $options): self
    {
        foreach (array_keys($options) as $key) {
            if (!\in_array($key, self::KNOWN_OPTIONS, true)) {
                throw new ConfigurationException(\sprintf(
                    'Unknown Nomba option "%s". Valid options: %s.',
                    $key,
                    implode(', ', self::KNOWN_OPTIONS),
                ));
            }
        }

        $clientId = self::stringOption($options, 'clientId', 'NOMBA_CLIENT_ID');
        $clientSecret = self::stringOption($options, 'clientSecret', 'NOMBA_CLIENT_SECRET');
        $accountId = self::stringOption($options, 'accountId', 'NOMBA_ACCOUNT_ID');

        $environment = $options['environment'] ?? self::env('NOMBA_ENVIRONMENT') ?? self::ENVIRONMENT_SANDBOX;
        if (!\is_string($environment) || !\array_key_exists($environment, self::BASE_URLS)) {
            throw new ConfigurationException(
                'Invalid environment: expected "sandbox" or "production" (option "environment" or env NOMBA_ENVIRONMENT).',
            );
        }

        $baseUrl = $options['baseUrl'] ?? self::BASE_URLS[$environment];
        if (!\is_string($baseUrl)) {
            throw new ConfigurationException('Option "baseUrl" must be a string.');
        }
        $baseUrl = self::validateBaseUrl($baseUrl);

        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        if (!\is_int($timeout) && !\is_float($timeout) || $timeout <= 0) {
            throw new ConfigurationException('Option "timeout" must be a positive number of seconds.');
        }

        $maxRetries = $options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES;
        if (!\is_int($maxRetries) || $maxRetries < 0 || $maxRetries > 10) {
            throw new ConfigurationException('Option "maxRetries" must be an integer from 0 to 10.');
        }

        $tokenStore = $options['tokenStore'] ?? new InMemoryTokenStore();
        if (!$tokenStore instanceof TokenStore) {
            throw new ConfigurationException('Option "tokenStore" must implement ' . TokenStore::class . '.');
        }

        $httpClient = $options['httpClient'] ?? null;
        if ($httpClient !== null && !$httpClient instanceof ClientInterface) {
            throw new ConfigurationException('Option "httpClient" must implement ' . ClientInterface::class . '.');
        }

        $sleeper = $options['sleeper'] ?? null;
        if ($sleeper !== null && !\is_callable($sleeper)) {
            throw new ConfigurationException('Option "sleeper" must be callable.');
        }

        return new self(
            $clientId,
            $clientSecret,
            $accountId,
            $environment,
            $baseUrl,
            (float) $timeout,
            $maxRetries,
            $tokenStore,
            $httpClient,
            $sleeper === null ? null : static function (float $seconds) use ($sleeper): void {
                $sleeper($seconds);
            },
        );
    }

    public function isSandbox(): bool
    {
        return $this->environment === self::ENVIRONMENT_SANDBOX;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '[redacted]',
            'accountId' => $this->accountId,
            'environment' => $this->environment,
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
            'maxRetries' => $this->maxRetries,
            'tokenStore' => $this->tokenStore::class,
            'httpClient' => $this->httpClient === null ? null : $this->httpClient::class,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function stringOption(#[\SensitiveParameter] array $options, string $name, string $envVar): string
    {
        $value = $options[$name] ?? self::env($envVar);
        if ($value === null || $value === '') {
            throw new ConfigurationException(\sprintf(
                'Missing Nomba credential: pass the "%s" option or set the %s environment variable.',
                $name,
                $envVar,
            ));
        }
        if (!\is_string($value)) {
            throw new ConfigurationException(\sprintf('Option "%s" must be a string.', $name));
        }

        return $value;
    }

    private static function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    private static function validateBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        $scheme = \is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        $host = \is_array($parts) ? strtolower($parts['host'] ?? '') : '';
        $local = \in_array($host, ['localhost', '127.0.0.1'], true);

        if (
            !\is_array($parts)
            || $host === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !($scheme === 'https' || ($scheme === 'http' && $local))
        ) {
            throw new ConfigurationException(
                'Invalid baseUrl: it must be an https:// URL (http:// is allowed only for localhost and 127.0.0.1) with no credentials, query or fragment.',
            );
        }

        return rtrim($baseUrl, '/');
    }
}
