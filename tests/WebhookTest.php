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
        $this->expectExceptionMessage('not a valid timestamp');
        Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => 'not-a-date'], 'k', 300);
    }

    public function testStringNullResponseCodeIsEmpty(): void
    {
        $body = '{"event_type":"payment_failed","requestId":"r","data":{"merchant":{"userId":"u","walletId":"w"},'
            . '"transaction":{"transactionId":"t","type":"x","time":"z","responseCode":"null"}}}';
        $signature = base64_encode(hash_hmac('sha256', 'payment_failed:r:u:w:t:x:z::ts', 'k', true));

        self::assertSame('payment_failed', Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => 'ts'], 'k')['event_type']);
    }

    public function testNonStringFieldsAreStringified(): void
    {
        $body = '{"event_type":"payment_success","requestId":"r","data":{"merchant":{"userId":42,"walletId":true},'
            . '"transaction":{"transactionId":"t","type":["x"],"time":"z","responseCode":"00"}}}';
        $signature = base64_encode(hash_hmac('sha256', 'payment_success:r:42:true:t::z:00:ts', 'k', true));

        self::assertSame('r', Webhook::verify($body, ['nomba-signature' => $signature, 'nomba-timestamp' => 'ts'], 'k')['requestId']);
    }

    public function testEventTypeConstants(): void
    {
        self::assertSame(
            ['payment_success', 'payment_failed', 'payment_reversal', 'payout_success', 'payout_failed', 'payout_refund'],
            WebhookEventType::ALL,
        );
    }
}
