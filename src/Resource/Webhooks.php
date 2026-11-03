<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\WebhookVerificationException;
use Nomba\Webhook;

/**
 * Webhook verification on the client. Same as the static {@see Webhook::verify()},
 * which needs no API credentials.
 *
 * @phpstan-import-type WebhookEvent from \Nomba\Types
 */
final class Webhooks
{
    /**
     * @param array<array-key, string|list<string>> $headers
     *
     * @return WebhookEvent
     *
     * @throws WebhookVerificationException
     */
    public function verify(
        string $rawBody,
        array $headers,
        #[\SensitiveParameter]
        string $signingKey,
        ?int $toleranceSeconds = null,
    ): array {
        return Webhook::verify($rawBody, $headers, $signingKey, $toleranceSeconds);
    }
}
