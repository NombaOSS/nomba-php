<?php

declare(strict_types=1);

namespace Nomba\Tests;

use Nomba\Exception\WebhookVerificationException;
use Nomba\Webhook;
use Nomba\WebhookEventType;

final class WebhookTest extends TestCase
{
    private const KEY = 'SampleSignatureKey123';
    private const TIMESTAMP = '2025-09-29T10:51:44Z';
    private const DOCUMENTED_SIGNATURE = 'q30yFZDyykoymBf9+mt1wz5UAuLk57raPGPHlGzIv00=';
    /** Same as the documented signature with the first letter's case changed: must not verify. */
    private const UPPER_SIGNATURE = 'Q30yFZDyykoymBf9+mt1wz5UAuLk57raPGPHlGzIv00=';

    private static function fixture(): string
    {
        $raw = file_get_contents(__DIR__ . '/Fixtures/webhook-fixture.json');
        self::assertIsString($raw);

        return $raw;
    }

    /**
     * @return array<string, string>
     */
    private static function headers(string $signature = self::DOCUMENTED_SIGNATURE): array
    {
        return ['nomba-signature' => $signature, 'nomba-timestamp' => self::TIMESTAMP];
    }

    public function testDocumentedVectorVerifies(): void
    {
        $event = Webhook::verify(self::fixture(), self::headers(), self::KEY);

        self::assertSame(WebhookEventType::PAYMENT_SUCCESS, $event['event_type']);
        self::assertSame('9d3b6f1e-2c4a-4e8b-a7d5-1f0c3e9b8a26', $event['requestId']);
    }

    public function testCaseAlteredSignatureFails(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('signature does not match');
        Webhook::verify(self::fixture(), self::headers(self::UPPER_SIGNATURE), self::KEY);
    }

    public function testSurroundingWhitespaceIsIgnored(): void
    {
        $event = Webhook::verify(self::fixture(), self::headers(' ' . self::DOCUMENTED_SIGNATURE . "\n"), self::KEY);

        self::assertSame('payment_success', $event['event_type']);
    }

    public function testComputedSignatureMatchesDocumentedSignatureExactly(): void
    {
        $message = 'payment_success:9d3b6f1e-2c4a-4e8b-a7d5-1f0c3e9b8a26:3c8e1f72-5a9d-4b16-8e2f-7d4c0a9b6e53:'
            . '66f2a91c4be7d3a5c1e08b47:API-VACT_TRA-3C8E1-a4f7c2d9-6b1e-4f38-9c5a-2e8d7b0f1c64:vact_transfer:'
            . '2025-09-29T10:51:44Z::' . self::TIMESTAMP;
        $computed = base64_encode(hash_hmac('sha256', $message, self::KEY, true));

        self::assertSame(self::DOCUMENTED_SIGNATURE, $computed);
    }

    public function testClientMethodDelegates(): void
    {
        $nomba = $this->nomba();
        $event = $nomba->webhooks->verify(self::fixture(), self::headers(), self::KEY);

        self::assertSame('payment_success', $event['event_type']);
        self::assertSame(0, $this->requestCount());
    }

    public function testHeadersAreCaseInsensitiveAndAcceptPsr7AndServerForms(): void
    {
        Webhook::verify(self::fixture(), ['Nomba-Signature' => [self::DOCUMENTED_SIGNATURE], 'NOMBA-TIMESTAMP' => [self::TIMESTAMP]], self::KEY);
        Webhook::verify(self::fixture(), ['HTTP_NOMBA_SIGNATURE' => self::DOCUMENTED_SIGNATURE, 'HTTP_NOMBA_TIMESTAMP' => self::TIMESTAMP], self::KEY);
        $event = Webhook::verify(self::fixture(), ['nomba-sig-value' => self::DOCUMENTED_SIGNATURE, 'nomba-timestamp' => self::TIMESTAMP], self::KEY);

        self::assertSame('payment_success', $event['event_type']);
    }

    public function testAlgorithmHeader(): void
    {
        $headers = self::headers() + ['nomba-signature-algorithm' => 'HmacSHA256'];
        self::assertSame('payment_success', Webhook::verify(self::fixture(), $headers, self::KEY)['event_type']);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('algorithm');
        Webhook::verify(self::fixture(), self::headers() + ['nomba-signature-algorithm' => 'HmacSHA1'], self::KEY);
    }

    public function testWrongKeyFails(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('signature does not match');
        Webhook::verify(self::fixture(), self::headers(), 'WrongKey');
    }

    public function testTamperedTransactionIdFails(): void
    {
        $tampered = str_replace('API-VACT_TRA-3C8E1-a4f7c2d9', 'API-VACT_TRA-3C8E1-ffffffff', self::fixture());
        self::assertNotSame(self::fixture(), $tampered);

        $this->expectException(WebhookVerificationException::class);
        Webhook::verify($tampered, self::headers(), self::KEY);
    }

    public function testTamperedTimestampHeaderFails(): void
    {
        $this->expectException(WebhookVerificationException::class);
        Webhook::verify(self::fixture(), ['nomba-signature' => self::DOCUMENTED_SIGNATURE, 'nomba-timestamp' => '2025-09-29T10:51:45Z'], self::KEY);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function missingHeaders(): iterable
    {
        yield 'no signature' => [['nomba-timestamp' => self::TIMESTAMP], 'nomba-signature'];
        yield 'empty signature' => [['nomba-signature' => '', 'nomba-timestamp' => self::TIMESTAMP], 'nomba-signature'];
        yield 'no timestamp' => [['nomba-signature' => self::DOCUMENTED_SIGNATURE], 'nomba-timestamp'];
        yield 'none' => [[], 'nomba-signature'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('missingHeaders')]
    public function testMissingHeadersFail(array $headers, string $message): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage($message);
        Webhook::verify(self::fixture(), $headers, self::KEY);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badBodies(): iterable
    {
        yield 'not json' => ['not json'];
        yield 'json scalar' => ['"hello"'];
        yield 'missing event_type' => ['{"requestId":"r","data":{"merchant":{},"transaction":{}}}'];
        yield 'missing requestId' => ['{"event_type":"payment_success","data":{"merchant":{},"transaction":{}}}'];
        yield 'missing merchant' => ['{"event_type":"payment_success","requestId":"r","data":{"transaction":{}}}'];
        yield 'missing transaction' => ['{"event_type":"payment_success","requestId":"r","data":{"merchant":{}}}'];
        yield 'missing data' => ['{"event_type":"payment_success","requestId":"r"}'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badBodies')]
    public function testUnsignableBodiesFail(string $body): void
    {
        $this->expectException(WebhookVerificationException::class);
        Webhook::verify($body, self::headers(), self::KEY);
    }

    public function testEmptyKeyFails(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('signing key');
        Webhook::verify(self::fixture(), self::headers(), '');
    }

    public function testToleranceRejectsOldTimestamp(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('tolerance');
        Webhook::verify(self::fixture(), self::headers(), self::KEY, 300);
    }

    public function testToleranceAcceptsFreshTimestampAndRejectsInvalidOne(): void
    {
        $body = '{"event_type":"payout_success","requestId":"req-1","data":{"merchant":{"userId":"u","walletId":"w"},'
            . '"transaction":{"transactionId":"t","type":"transfer","time":"2026-01-01T00:00:00Z","responseCode":null}}}';
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature = base64_encode(hash_hmac('sha256', "payout_success:req-1:u:w:t:transfer:2026-01-01T00:00:00Z::{$timestamp}", 'k', true));

        $event = Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => $timestamp], 'k', 300);
        self::assertSame(WebhookEventType::PAYOUT_SUCCESS, $event['event_type']);

        $signature = base64_encode(hash_hmac('sha256', 'payout_success:req-1:u:w:t:transfer:2026-01-01T00:00:00Z::not-a-date', 'k', true));
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('not an RFC 3339 timestamp');
        Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => 'not-a-date'], 'k', 300);
    }

    public function testStringNullResponseCodeIsEmpty(): void
    {
        $body = '{"event_type":"payment_failed","requestId":"r","data":{"merchant":{"userId":"u","walletId":"w"},'
            . '"transaction":{"transactionId":"t","type":"x","time":"z","responseCode":"null"}}}';
        $signature = base64_encode(hash_hmac('sha256', 'payment_failed:r:u:w:t:x:z::' . self::TIMESTAMP, 'k', true));

        self::assertSame('payment_failed', Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => self::TIMESTAMP], 'k')['event_type']);
    }

    public function testNonStringFieldsAreStringified(): void
    {
        $body = '{"event_type":"payment_success","requestId":"r","data":{"merchant":{"userId":42,"walletId":true},'
            . '"transaction":{"transactionId":"t","type":["x"],"time":"z","responseCode":"00"}}}';
        $signature = base64_encode(hash_hmac('sha256', 'payment_success:r:42:true:t::z:00:' . self::TIMESTAMP, 'k', true));

        self::assertSame('r', Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => self::TIMESTAMP], 'k')['requestId']);
    }

    public function testEventTypeConstants(): void
    {
        self::assertSame(
            ['payment_success', 'payment_failed', 'payment_reversal', 'payout_success', 'payout_failed', 'payout_refund'],
            WebhookEventType::ALL,
        );
    }

    /**
     * Sign an event the way Nomba does.
     *
     * @param array{transactionId: string, type: string, time: string, responseCode: string, ...} $tx
     */
    private static function sign(array $tx, string $timestamp, string $key = 'k'): string
    {
        $message = implode(':', ['payment_success', 'r1', 'u', 'w', $tx['transactionId'], $tx['type'], $tx['time'], $tx['responseCode'], $timestamp]);

        return base64_encode(hash_hmac('sha256', $message, $key, true));
    }

    /**
     * @param array<string, mixed> $tx
     */
    private static function event(array $tx): string
    {
        return json_encode(['event_type' => 'payment_success', 'requestId' => 'r1', 'data' => ['merchant' => ['userId' => 'u', 'walletId' => 'w'], 'transaction' => $tx]], \JSON_THROW_ON_ERROR);
    }

    /**
     * An attacker holding one genuine webhook moves the front of the signed
     * timestamp into responseCode. The HMAC input is unchanged, so the
     * signature still matches; the SDK must reject it anyway.
     */
    public function testSplitTimestampForgeryIsRejected(): void
    {
        $tx = ['transactionId' => 't1', 'type' => 'vact_transfer', 'time' => '2025-09-29T10:51:44Z', 'responseCode' => '', 'transactionAmount' => 5000];
        $genuineTimestamp = '2025-09-29T10:' . gmdate('H') . ':' . gmdate('i') . 'Z';
        $signature = self::sign($tx, $genuineTimestamp);
        self::assertSame('payment_success', Webhook::verify(self::event($tx), ['nomba-signature' => $signature, 'nomba-timestamp' => $genuineTimestamp], 'k')['event_type']);

        [$front, $back] = explode(':', $genuineTimestamp, 2);
        $forged = ['responseCode' => ':' . $front] + $tx;
        self::assertSame($signature, self::sign($forged, $back), 'the forgery has the same signed message');

        foreach ([null, 300] as $tolerance) {
            try {
                Webhook::verify(self::event($forged), ['nomba-signature' => $signature, 'nomba-timestamp' => $back], 'k', $tolerance);
                self::fail('Forged webhook was accepted');
            } catch (WebhookVerificationException $e) {
                self::assertStringContainsString('RFC 3339', $e->getMessage());
            }
        }
    }

    public function testResponseCodeWithColonIsRejected(): void
    {
        $tx = ['transactionId' => 't1', 'type' => 'x', 'time' => 'z', 'responseCode' => 'a:b'];
        $signature = self::sign($tx, self::TIMESTAMP);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('responseCode must not contain ":"');
        Webhook::verify(self::event($tx), ['nomba-signature' => $signature, 'nomba-timestamp' => self::TIMESTAMP], 'k');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonRfc3339Timestamps(): iterable
    {
        yield 'time only' => ['51:44Z'];
        yield 'no zone' => ['2025-09-29T10:51:44'];
        yield 'space separator' => ['2025-09-29 10:51:44Z'];
        yield 'lower-case t' => ['2025-09-29t10:51:44Z'];
        yield 'no seconds' => ['2025-09-29T10:51Z'];
        yield 'unix seconds' => ['1759143104'];
        yield 'http date' => ['Mon, 29 Sep 2025 10:51:44 GMT'];
        yield 'trailing junk' => ['2025-09-29T10:51:44Z x'];
        yield 'trailing newline in value' => ["2025-09-29T10:51:44Z\n:"];
        yield 'month 13' => ['2025-13-29T10:51:44Z'];
        yield 'hour 24' => ['2025-09-29T24:00:00Z'];
        yield 'february 30' => ['2025-02-30T10:51:44Z'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonRfc3339Timestamps')]
    public function testNonRfc3339TimestampIsRejectedWithAndWithoutTolerance(string $timestamp): void
    {
        $tx = ['transactionId' => 't1', 'type' => 'x', 'time' => 'z', 'responseCode' => ''];
        $signature = self::sign($tx, $timestamp);

        foreach ([null, 300] as $tolerance) {
            try {
                Webhook::verify(self::event($tx), ['nomba-signature' => $signature, 'nomba-timestamp' => $timestamp], 'k', $tolerance);
                self::fail('Accepted timestamp ' . $timestamp);
            } catch (WebhookVerificationException $e) {
                self::assertStringContainsString('nomba-timestamp', $e->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rfc3339Timestamps(): iterable
    {
        yield 'utc' => ['2025-09-29T10:51:44Z'];
        yield 'fraction' => ['2025-09-29T10:51:44.123456789Z'];
        yield 'offset' => ['2025-09-29T11:51:44+01:00'];
        yield 'negative offset' => ['2025-09-29T05:51:44-05:00'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rfc3339Timestamps')]
    public function testRfc3339TimestampsAreAccepted(string $timestamp): void
    {
        $tx = ['transactionId' => 't1', 'type' => 'x', 'time' => 'z', 'responseCode' => ''];

        $event = Webhook::verify(self::event($tx), ['nomba-signature' => self::sign($tx, $timestamp), 'nomba-timestamp' => $timestamp], 'k');

        self::assertSame('r1', $event['requestId']);
    }
}
