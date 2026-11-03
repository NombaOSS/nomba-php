<?php

declare(strict_types=1);

namespace Nomba\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Exception\NombaException;
use Nomba\Exception\ServerException;
use Nomba\Exception\TimeoutException;
use Nomba\Internal\Uuid;
use Nomba\Nomba;

final class IdempotencyTest extends TestCase
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * @return iterable<string, array{\Closure(Nomba): array<mixed>}>
     */
    public static function moneyMovers(): iterable
    {
        yield 'transfers.toBank' => [static fn(Nomba $n): array => $n->transfers->toBank(['amount' => 1, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b'])];
        yield 'transfers.toWallet' => [static fn(Nomba $n): array => $n->transfers->toWallet(['amount' => 1, 'receiverAccountId' => 'a'])];
        yield 'bills.buyAirtime' => [static fn(Nomba $n): array => $n->bills->buyAirtime(['amount' => 1, 'phoneNumber' => 'p', 'network' => 'MTN'])];
        yield 'bills.buyData' => [static fn(Nomba $n): array => $n->bills->buyData(['productId' => 'x', 'phoneNumber' => 'p', 'network' => 'MTN'])];
        yield 'bills.buyElectricity' => [static fn(Nomba $n): array => $n->bills->buyElectricity(['disco' => 'd', 'customerId' => 'c', 'amount' => 1])];
        yield 'bills.payCableTv' => [static fn(Nomba $n): array => $n->bills->payCableTv(['cableTvType' => 'DSTV', 'customerId' => 'c', 'amount' => 1])];
        yield 'bills.fundBetting' => [static fn(Nomba $n): array => $n->bills->fundBetting(['bettingProvider' => 'b', 'customerId' => 'c', 'amount' => 1])];
    }

    /**
     * @param \Closure(Nomba): array<mixed> $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('moneyMovers')]
    public function testGeneratesMerchantTxRefAndReturnsIt(\Closure $call): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['id' => 'tx', 'status' => 'PENDING_BILLING'])]);

        $result = $call($nomba);

        $sent = $this->body(1)['merchantTxRef'] ?? null;
        self::assertIsString($sent);
        self::assertMatchesRegularExpression(self::UUID_V4, $sent);
        self::assertSame($sent, $result['merchantTxRef']);
        self::assertSame('PENDING_BILLING', $result['status']);
    }

    public function testKeepsCallerReferenceAndApiEcho(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['id' => 'tx']), self::ok(['merchantTxRef' => 'from-api']), self::json(201, ['code' => '201', 'data' => null])]);

        $first = $nomba->transfers->toBank(['amount' => 1, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b', 'merchantTxRef' => 'mine-1']);
        $second = $nomba->bills->buyAirtime(['amount' => 1, 'phoneNumber' => 'p', 'network' => 'MTN', 'merchantTxRef' => 'mine-2']);
        $third = $nomba->transfers->toWallet(['amount' => 1, 'receiverAccountId' => 'a', 'merchantTxRef' => 'mine-3']);

        self::assertSame('mine-1', $this->body(1)['merchantTxRef'] ?? null);
        self::assertSame('mine-1', $first['merchantTxRef']);
        self::assertSame('from-api', $second['merchantTxRef']);
        self::assertSame(['merchantTxRef' => 'mine-3'], $third);
    }

    public function testEachCallGetsAFreshReference(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([]), self::ok([])]);

        $a = $nomba->transfers->toWallet(['amount' => 1, 'receiverAccountId' => 'a']);
        $b = $nomba->transfers->toWallet(['amount' => 1, 'receiverAccountId' => 'a']);

        self::assertNotSame($a['merchantTxRef'], $b['merchantTxRef']);
    }

    public function testChargeSavedCardUsesOrderReference(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['status' => true, 'otpRequired' => false]), self::ok(['status' => true])]);
        $order = ['callbackUrl' => 'https://example.com', 'customerEmail' => 'c@example.com', 'amount' => 10, 'currency' => 'NGN'];

        $result = $nomba->checkout->chargeSavedCard(['tokenKey' => 'tk', 'order' => $order]);

        $sent = $this->body(1);
        self::assertIsArray($sent);
        self::assertIsArray($sent['order']);
        self::assertIsString($sent['order']['orderReference']);
        self::assertMatchesRegularExpression(self::UUID_V4, $sent['order']['orderReference']);
        self::assertArrayNotHasKey('merchantTxRef', $sent);
        self::assertSame($sent['order']['orderReference'], $result['orderReference'] ?? null);
        self::assertArrayNotHasKey('merchantTxRef', $result);

        $withoutOrder = $nomba->checkout->chargeSavedCard(['tokenKey' => 'tk']);
        self::assertSame(['tokenKey' => 'tk'], $this->body(2), 'no order object is invented');
        self::assertSame(['status' => true], $withoutOrder);
    }

    public function testUuidV4Format(): void
    {
        $seen = [];
        for ($i = 0; $i < 100; ++$i) {
            $uuid = Uuid::v4();
            self::assertMatchesRegularExpression(self::UUID_V4, $uuid);
            $seen[$uuid] = true;
        }
        self::assertCount(100, $seen);
    }

    /**
     * @return iterable<string, array{\Closure(Nomba): mixed, \Throwable|\GuzzleHttp\Psr7\Response, class-string<NombaException>}>
     */
    public static function failures(): iterable
    {
        $timeout = new ConnectException('cURL error 28: Operation timed out', new Request('POST', 'https://sandbox.nomba.com/'), null, ['errno' => 28]);
        $server = self::json(500, ['code' => '500', 'description' => 'Server error']);
        $toBank = static fn(Nomba $n): mixed => $n->transfers->toBank(['amount' => 1, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b']);
        $airtime = static fn(Nomba $n): mixed => $n->bills->buyAirtime(['amount' => 1, 'phoneNumber' => 'p', 'network' => 'MTN']);

        yield 'toBank timeout' => [$toBank, $timeout, TimeoutException::class];
        yield 'toBank 5xx' => [$toBank, $server, ServerException::class];
        yield 'buyAirtime timeout' => [$airtime, $timeout, TimeoutException::class];
        yield 'buyAirtime 5xx' => [$airtime, $server, ServerException::class];
        yield 'toWallet non-success code' => [static fn(Nomba $n): mixed => $n->transfers->toWallet(['amount' => 1, 'receiverAccountId' => 'a']), self::ok(null, '99'), ApiException::class];
    }

    /**
     * @param \Closure(Nomba): mixed        $call
     * @param class-string<NombaException> $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('failures')]
    public function testGeneratedReferenceReachesCallerOnFailure(\Closure $call, \Throwable|\GuzzleHttp\Psr7\Response $failure, string $class): void
    {
        $nomba = $this->nomba([self::tokenResponse(), $failure]);

        try {
            $call($nomba);
            self::fail('Expected ' . $class);
        } catch (NombaException $e) {
            self::assertSame($class, $e::class);
            $reference = $e->getReference();
            self::assertNotNull($reference);
            self::assertSame('merchantTxRef', $reference->field);
            self::assertSame($this->body(1)['merchantTxRef'] ?? null, $reference->value);
            self::assertMatchesRegularExpression(self::UUID_V4, $reference->value);
            self::assertSame(2, $this->requestCount(), 'not retried');
        }
    }

    public function testCallerReferenceReachesCallerOnTimeout(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), new ConnectException('timed out', new Request('POST', 'https://x/'), null, ['errno' => 28])]);

        try {
            $nomba->bills->buyElectricity(['disco' => 'd', 'customerId' => 'c', 'amount' => 1, 'merchantTxRef' => 'mine-9'], ['subAccountId' => 's1']);
            self::fail('Expected TimeoutException');
        } catch (TimeoutException $e) {
            self::assertInstanceOf(ConnectionException::class, $e);
            self::assertSame('mine-9', $e->getReference()?->value);
            self::assertInstanceOf(ConnectException::class, $e->getPrevious(), 'class and previous are preserved');
        }
    }

    public function testChargeSavedCardReferenceOnFailure(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(503, []), self::json(502, [])]);
        $order = ['callbackUrl' => 'https://example.com', 'customerEmail' => 'c@example.com', 'amount' => 10, 'currency' => 'NGN'];

        try {
            $nomba->checkout->chargeSavedCard(['tokenKey' => 'tk', 'order' => $order]);
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            $sent = $this->body(1);
            self::assertIsArray($sent);
            self::assertIsArray($sent['order']);
            self::assertSame('order.orderReference', $e->getReference()?->field);
            self::assertSame($sent['order']['orderReference'], $e->getReference()->value);
        }

        try {
            $nomba->checkout->chargeSavedCard(['tokenKey' => 'tk']);
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertNull($e->getReference(), 'no order, no reference');
        }
    }

    public function testOtherCallsHaveNoReferenceAndReferenceIsSetOnce(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::json(500, [])], ['maxRetries' => 0]);

        try {
            $nomba->accounts->getParent();
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertNull($e->getReference());
            $attached = $e->withReference(new \Nomba\Exception\Reference('merchantTxRef', 'a'));
            self::assertSame($e, $attached);
            $attached->withReference(new \Nomba\Exception\Reference('merchantTxRef', 'b'));
            self::assertSame('a', $attached->getReference()?->value);
        }
    }
}
