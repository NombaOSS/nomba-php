<?php

declare(strict_types=1);

namespace Nomba\Tests;

use Nomba\Auth\InMemoryTokenStore;
use Nomba\Config;
use Nomba\Exception\ConfigurationException;
use Nomba\Nomba;

final class ConfigTest extends TestCase
{
    private const ENV = ['NOMBA_CLIENT_ID', 'NOMBA_CLIENT_SECRET', 'NOMBA_ACCOUNT_ID', 'NOMBA_ENVIRONMENT'];

    protected function setUp(): void
    {
        $this->clearEnv();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
    }

    private function clearEnv(): void
    {
        foreach (self::ENV as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    /**
     * @return array{clientId: string, clientSecret: string, accountId: string}
     */
    private static function creds(): array
    {
        return ['clientId' => 'id', 'clientSecret' => 'secret', 'accountId' => 'acct'];
    }

    public function testDefaultsToSandbox(): void
    {
        $config = (new Nomba(self::creds()))->getConfig();

        self::assertSame('sandbox', $config->environment);
        self::assertSame('https://sandbox.nomba.com', $config->baseUrl);
        self::assertSame(30.0, $config->timeout);
        self::assertSame(2, $config->maxRetries);
        self::assertInstanceOf(InMemoryTokenStore::class, $config->tokenStore);
        self::assertTrue($config->isSandbox());
    }

    public function testProductionEnvironment(): void
    {
        $config = Config::fromOptions(self::creds() + ['environment' => 'production']);

        self::assertSame('https://api.nomba.com', $config->baseUrl);
        self::assertFalse($config->isSandbox());
    }

    public function testReadsEnvironmentVariables(): void
    {
        putenv('NOMBA_CLIENT_ID=env-id');
        $_ENV['NOMBA_CLIENT_SECRET'] = 'env-secret';
        $_SERVER['NOMBA_ACCOUNT_ID'] = 'env-acct';
        putenv('NOMBA_ENVIRONMENT=production');

        $config = Config::fromOptions([]);

        self::assertSame('env-id', $config->clientId);
        self::assertSame('env-secret', $config->clientSecret);
        self::assertSame('env-acct', $config->accountId);
        self::assertSame('production', $config->environment);
    }

    public function testOptionsOverrideEnvironment(): void
    {
        putenv('NOMBA_CLIENT_ID=env-id');

        self::assertSame('id', Config::fromOptions(self::creds())->clientId);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missing(): iterable
    {
        yield 'clientId' => ['clientId', 'NOMBA_CLIENT_ID'];
        yield 'clientSecret' => ['clientSecret', 'NOMBA_CLIENT_SECRET'];
        yield 'accountId' => ['accountId', 'NOMBA_ACCOUNT_ID'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missing')]
    public function testMissingCredentialNamesOptionAndEnvVar(string $option, string $env): void
    {
        $options = self::creds();
        unset($options[$option]);

        try {
            new Nomba($options);
            self::fail('Expected ConfigurationException');
        } catch (ConfigurationException $e) {
            self::assertStringContainsString('"' . $option . '"', $e->getMessage());
            self::assertStringContainsString($env, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'environment' => [['environment' => 'staging'], 'environment'];
        yield 'environment type' => [['environment' => 1], 'environment'];
        yield 'http base url' => [['baseUrl' => 'http://api.example.com'], 'baseUrl'];
        yield 'base url with query' => [['baseUrl' => 'https://api.example.com?x=1'], 'baseUrl'];
        yield 'base url with credentials' => [['baseUrl' => 'https://user:pass@api.example.com'], 'baseUrl'];
        yield 'base url not a url' => [['baseUrl' => 'not a url'], 'baseUrl'];
        yield 'base url type' => [['baseUrl' => 5], 'baseUrl'];
        yield 'timeout zero' => [['timeout' => 0], 'timeout'];
        yield 'timeout type' => [['timeout' => '30'], 'timeout'];
        yield 'max retries negative' => [['maxRetries' => -1], 'maxRetries'];
        yield 'max retries too high' => [['maxRetries' => 11], 'maxRetries'];
        yield 'token store' => [['tokenStore' => new \stdClass()], 'tokenStore'];
        yield 'http client' => [['httpClient' => new \stdClass()], 'httpClient'];
        yield 'sleeper' => [['sleeper' => 'not-callable-function-name'], 'sleeper'];
        yield 'unknown option' => [['client_id' => 'x'], 'Unknown Nomba option "client_id"'];
        yield 'credential type' => [['clientId' => 123], '"clientId" must be a string'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function testInvalidOptions(array $options, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);
        Config::fromOptions($options + self::creds());
    }

    public function testLocalhostMayUseHttp(): void
    {
        self::assertSame('http://localhost:8080', Config::fromOptions(self::creds() + ['baseUrl' => 'http://localhost:8080/'])->baseUrl);
        self::assertSame('http://127.0.0.1:9000/api', Config::fromOptions(self::creds() + ['baseUrl' => 'http://127.0.0.1:9000/api'])->baseUrl);
        self::assertSame('https://proxy.example.com', Config::fromOptions(self::creds() + ['baseUrl' => 'https://proxy.example.com'])->baseUrl);
    }

    public function testDebugOutputRedactsSecret(): void
    {
        $nomba = new Nomba(self::creds() + ['clientSecret' => 'super-secret-value']);

        $dump = print_r($nomba, true);
        ob_start();
        var_dump($nomba);
        $dump .= (string) ob_get_clean();

        self::assertStringNotContainsString('super-secret-value', $dump);
        self::assertStringContainsString('[redacted]', $dump);
        self::assertStringContainsString('"clientId"', $dump);
    }

    public function testVersionConstant(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Nomba::VERSION);
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true);
        self::assertIsArray($composer);
        self::assertArrayNotHasKey('version', $composer, 'The version lives in Nomba::VERSION and the git tag');
    }

    public function testConstructorMakesNoRequests(): void
    {
        $this->nomba([]);

        self::assertSame(0, $this->requestCount());
    }
}
