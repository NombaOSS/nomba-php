<?php

declare(strict_types=1);

namespace Nomba\Tests\Smoke;

use Nomba\Nomba;
use PHPUnit\Framework\TestCase;

/**
 * Opt-in check against the real Nomba sandbox. Skipped unless
 * NOMBA_SANDBOX_CLIENT_ID, NOMBA_SANDBOX_CLIENT_SECRET and
 * NOMBA_SANDBOX_ACCOUNT_ID are set. Never runs in CI.
 *
 *     composer smoke
 */
final class SandboxSmokeTest extends TestCase
{
    public function testIssuesTokenAndFetchesParentAccount(): void
    {
        $clientId = getenv('NOMBA_SANDBOX_CLIENT_ID');
        $clientSecret = getenv('NOMBA_SANDBOX_CLIENT_SECRET');
        $accountId = getenv('NOMBA_SANDBOX_ACCOUNT_ID');
        if (!\is_string($clientId) || $clientId === '' || !\is_string($clientSecret) || $clientSecret === '' || !\is_string($accountId) || $accountId === '') {
            self::markTestSkipped('Set NOMBA_SANDBOX_CLIENT_ID, NOMBA_SANDBOX_CLIENT_SECRET and NOMBA_SANDBOX_ACCOUNT_ID to run.');
        }

        $nomba = new Nomba([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'accountId' => $accountId,
            'environment' => 'sandbox',
        ]);

        $parent = $nomba->accounts->getParent();

        self::assertNotSame('', $parent['accountId']);
        $nomba->auth->revoke();
    }
}
