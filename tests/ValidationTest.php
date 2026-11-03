<?php

declare(strict_types=1);

namespace Nomba\Tests;

use Nomba\Exception\ApiException;
use Nomba\Exception\ValidationException;
use Nomba\Internal\Params;
use Nomba\Nomba;

final class ValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function badSegments(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'dot' => ['.'];
        yield 'dot dot' => ['..'];
        yield 'slash' => ['../v2/transfers/bank'];
        yield 'leading slash' => ['/etc'];
        yield 'backslash' => ['a\\b'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badSegments')]
    public function testPathParametersAreValidatedBeforeSending(string $value): void
    {
        $calls = [
            static fn(Nomba $n) => $n->virtualAccounts->get($value),
            static fn(Nomba $n) => $n->checkout->getOrder($value),
            static fn(Nomba $n) => $n->transactions->requery($value),
            static fn(Nomba $n) => $n->accounts->getBalance(['subAccountId' => $value]),
            static fn(Nomba $n) => $n->transfers->toBank(['amount' => 1, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b'], ['subAccountId' => $value]),
            static fn(Nomba $n) => $n->bills->listDataPlans($value),
            static fn(Nomba $n) => $n->terminals->sendPaymentRequest($value, ['merchantTxRef' => 'r', 'amount' => 1, 'currency' => 'NGN']),
            static fn(Nomba $n) => $n->globalPayout->getAccount($value),
        ];
        foreach ($calls as $call) {
            $nomba = $this->nomba([]);
            try {
                $call($nomba);
                self::fail('Expected ValidationException for ' . var_export($value, true));
            } catch (ValidationException $e) {
                self::assertInstanceOf(ApiException::class, $e);
                self::assertNull($e->httpStatus);
                self::assertSame(0, $this->requestCount(), 'nothing is sent');
            }
        }
    }

    public function testSegmentsAreUrlEncoded(): void
    {
        self::assertSame('a%3Fb%23c%25d%20e', Params::segment('a?b#c%d e', 'x'));
        self::assertSame('42', Params::segment(42, 'x'));
        self::assertSame('...', Params::segment('...', 'x'));

        $this->expectException(ValidationException::class);
        Params::segment(['x'], 'x');
    }

    /**
     * @return iterable<string, array{\Closure(Nomba): mixed, string}>
     */
    public static function missingArguments(): iterable
    {
        yield 'getSubAccount none' => [static fn(Nomba $n) => $n->accounts->getSubAccount([]), 'exactly one of: accountId, accountRef'];
        yield 'getSubAccount both' => [static fn(Nomba $n) => $n->accounts->getSubAccount(['accountId' => 'a', 'accountRef' => 'b']), 'exactly one'];
        yield 'transactions.get none' => [static fn(Nomba $n) => $n->transactions->get(['subAccountId' => 's']), 'exactly one of: transactionRef, merchantTxRef, orderReference, orderId'];
        yield 'transactions.get two' => [static fn(Nomba $n) => $n->transactions->get(['orderId' => 'a', 'merchantTxRef' => 'b']), 'exactly one'];
        /** @phpstan-ignore argument.type */
        yield 'checkout.getTransaction' => [static fn(Nomba $n) => $n->checkout->getTransaction(['idType' => 'ORDER_ID']), 'id is required'];
        /** @phpstan-ignore argument.type */
        yield 'checkout.getSavedCards' => [static fn(Nomba $n) => $n->checkout->getSavedCards('ord', []), 'otp is required'];
        yield 'getMandateStatus' => [static fn(Nomba $n) => $n->directDebits->getMandateStatus(''), 'mandateId is required'];
        yield 'listCableTvPlans' => [static fn(Nomba $n) => $n->bills->listCableTvPlans(''), 'cableTvType is required'];
        /** @phpstan-ignore argument.type */
        yield 'lookupElectricityCustomer' => [static fn(Nomba $n) => $n->bills->lookupElectricityCustomer(['disco' => 'x']), 'customerId is required'];
        /** @phpstan-ignore argument.type */
        yield 'lookupCableTvCustomer' => [static fn(Nomba $n) => $n->bills->lookupCableTvCustomer(['customerId' => 'x']), 'cableTvType is required'];
        /** @phpstan-ignore argument.type */
        yield 'lookupBettingCustomer' => [static fn(Nomba $n) => $n->bills->lookupBettingCustomer(['customerId' => 'x']), 'providerId is required'];
        /** @phpstan-ignore argument.type */
        yield 'getExchangeRates' => [static fn(Nomba $n) => $n->globalPayout->getExchangeRates(['from' => 'NGN']), 'to is required'];
    }

    /**
     * @param \Closure(Nomba): mixed $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('missingArguments')]
    public function testMissingArgumentsAreValidationErrors(\Closure $call, string $message): void
    {
        $nomba = $this->nomba([]);

        try {
            $call($nomba);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString($message, $e->getMessage());
            self::assertSame(0, $this->requestCount());
        }
    }
}
