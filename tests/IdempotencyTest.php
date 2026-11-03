<?php

declare(strict_types=1);

namespace Nomba\Tests;

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
}
