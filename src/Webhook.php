<?php

declare(strict_types=1);

namespace Nomba;

use Nomba\Exception\WebhookVerificationException;

/**
 * Verify Nomba webhook signatures. Usable without API credentials.
 *
 * The signature is base64(HMAC-SHA256(signingKey, message)), where message is
 *
 *     event_type:requestId:data.merchant.userId:data.merchant.walletId:
 *     data.transaction.transactionId:data.transaction.type:data.transaction.time:
 *     data.transaction.responseCode:<nomba-timestamp header>
 *
 * (joined with ":"; a null/absent/"null" responseCode is the empty string).
 *
 * @phpstan-import-type WebhookEvent from Types
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'nomba-signature';
    public const LEGACY_SIGNATURE_HEADER = 'nomba-sig-value';
    public const TIMESTAMP_HEADER = 'nomba-timestamp';
    public const ALGORITHM_HEADER = 'nomba-signature-algorithm';
    public const ALGORITHM = 'HmacSHA256';

    private function __construct() {}

    /**
     * Verify a webhook and return the parsed event.
     *
     * @param string                                $rawBody          the request body exactly as received
     *                                              (for example file_get_contents('php://input')); never a re-encoded array
     * @param array<array-key, string|list<string>> $headers          header name => value (any case). PSR-7
     *                                              getHeaders() and $_SERVER (HTTP_NOMBA_SIGNATURE) forms both work.
     * @param string                                $signingKey       the webhook signing key from the Nomba dashboard
     * @param int|null                              $toleranceSeconds reject timestamps further than this from now
     *                                              (replay protection); null disables the check
     *
     * @return WebhookEvent
     *
     * @throws WebhookVerificationException
     */
    public static function verify(
        string $rawBody,
        array $headers,
        #[\SensitiveParameter]
        string $signingKey,
        ?int $toleranceSeconds = null,
    ): array {
        if ($signingKey === '') {
            throw new WebhookVerificationException('Webhook signing key is empty.');
        }

        $normalized = self::normalizeHeaders($headers);
        $signature = $normalized[self::SIGNATURE_HEADER] ?? $normalized[self::LEGACY_SIGNATURE_HEADER] ?? '';
        if ($signature === '') {
            throw new WebhookVerificationException('Missing nomba-signature header.');
        }
        $timestamp = $normalized[self::TIMESTAMP_HEADER] ?? '';
        if ($timestamp === '') {
            throw new WebhookVerificationException('Missing nomba-timestamp header.');
        }
        $algorithm = $normalized[self::ALGORITHM_HEADER] ?? null;
        if ($algorithm !== null && strcasecmp($algorithm, self::ALGORITHM) !== 0) {
            throw new WebhookVerificationException('Unsupported nomba-signature-algorithm; expected HmacSHA256.');
        }

        try {
            $event = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new WebhookVerificationException('Webhook body is not valid JSON.', 0, $e);
        }
        if (!\is_array($event)) {
            throw new WebhookVerificationException('Webhook body is not a JSON object.');
        }

        $eventType = $event['event_type'] ?? null;
        $requestId = $event['requestId'] ?? null;
        $data = $event['data'] ?? null;
        $merchant = \is_array($data) ? ($data['merchant'] ?? null) : null;
        $transaction = \is_array($data) ? ($data['transaction'] ?? null) : null;
        if (!\is_string($eventType) || !\is_string($requestId) || !\is_array($merchant) || !\is_array($transaction)) {
            throw new WebhookVerificationException(
                'Webhook body is missing event_type, requestId, data.merchant or data.transaction.',
            );
        }

        $responseCode = self::field($transaction, 'responseCode');
        if ($responseCode === 'null') {
            $responseCode = '';
        }

        $message = implode(':', [
            $eventType,
            $requestId,
            self::field($merchant, 'userId'),
            self::field($merchant, 'walletId'),
            self::field($transaction, 'transactionId'),
            self::field($transaction, 'type'),
            self::field($transaction, 'time'),
            $responseCode,
            $timestamp,
        ]);
        $expected = base64_encode(hash_hmac('sha256', $message, $signingKey, true));

        // Case-insensitive, as in Nomba's reference implementation.
        if (!hash_equals(strtolower($expected), strtolower(trim($signature)))) {
            throw new WebhookVerificationException('Webhook signature does not match.');
        }

        if ($toleranceSeconds !== null) {
            self::checkTimestamp($timestamp, $toleranceSeconds);
        }

        /** @var WebhookEvent $event */
        return $event;
    }

    /**
     * @param array<array-key, mixed> $headers
     *
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $name = str_replace('_', '-', strtolower((string) $name));
            if (str_starts_with($name, 'http-')) {
                $name = substr($name, 5);
            }
            if (\is_array($value)) {
                $first = reset($value);
                $value = $first === false ? '' : $first;
            }
            if (\is_string($value)) {
                $out[$name] = trim($value);
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $object
     */
    private static function field(array $object, string $key): string
    {
        $value = $object[$key] ?? null;

        return match (true) {
            $value === null => '',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * @throws WebhookVerificationException
     */
    private static function checkTimestamp(string $timestamp, int $toleranceSeconds): void
    {
        try {
            $time = (new \DateTimeImmutable($timestamp))->getTimestamp();
        } catch (\Exception $e) {
            throw new WebhookVerificationException('nomba-timestamp is not a valid timestamp.', 0, $e);
        }
        if (abs(time() - $time) > $toleranceSeconds) {
            throw new WebhookVerificationException('Webhook timestamp is outside the allowed tolerance.');
        }
    }
}
