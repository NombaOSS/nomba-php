<?php

declare(strict_types=1);

namespace Nomba\Tests;

use Nomba\Exception\ValidationException;
use Nomba\Nomba;

final class PaginationTest extends TestCase
{
    public function testListAllFollowsCursorsUntilEmpty(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::ok(['results' => [['id' => 't1'], ['id' => 't2']], 'cursor' => 'c2']),
            self::ok(['results' => [['id' => 't3']], 'cursor' => 'c3']),
            self::ok(['results' => [['id' => 't4']], 'cursor' => '']),
        ]);

        $generator = $nomba->transactions->listAll(['limit' => 2, 'dateFrom' => '2026-01-01']);
        self::assertInstanceOf(\Generator::class, $generator);
        self::assertSame(0, $this->requestCount(), 'lazy until iterated');

        $ids = [];
        foreach ($generator as $transaction) {
            $ids[] = $transaction['id'];
        }

        self::assertSame(['t1', 't2', 't3', 't4'], $ids);
        self::assertSame(['limit' => '2', 'dateFrom' => '2026-01-01'], $this->query(1));
        self::assertSame(['cursor' => 'c2', 'limit' => '2', 'dateFrom' => '2026-01-01'], $this->query(2));
        self::assertSame('c3', $this->query(3)['cursor']);
    }

    public function testStopsOnAbsentCursorEmptyPageOrRepeatedCursor(): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::ok(['results' => [['id' => 'a']]]),
            self::ok(['results' => [], 'cursor' => 'more']),
            self::ok(['results' => [['id' => 'b']], 'cursor' => 'same']),
            self::ok(['results' => [['id' => 'c']], 'cursor' => 'same']),
        ]);

        self::assertCount(1, iterator_to_array($nomba->transactions->listAllBankTransactions(), false));
        self::assertCount(0, iterator_to_array($nomba->virtualAccounts->listAll(), false));
        self::assertCount(2, iterator_to_array($nomba->accounts->listAllTerminals(), false));
        self::assertSame(5, $this->requestCount());
    }

    /**
     * @return iterable<string, array{\Closure(Nomba): \Generator<int, mixed>, string, string}>
     */
    public static function pagers(): iterable
    {
        yield 'transactions.listAll' => [static function (Nomba $n): \Generator {
            return $n->transactions->listAll(['subAccountId' => 's1']);
        }, 'GET', '/v1/transactions/accounts/s1'];
        yield 'transactions.filterAll' => [static function (Nomba $n): \Generator {
            return $n->transactions->filterAll(['status' => 'SUCCESS'], ['limit' => 50]);
        }, 'POST', '/v1/transactions/accounts'];
        yield 'transactions.listAllBankTransactions' => [static function (Nomba $n): \Generator {
            return $n->transactions->listAllBankTransactions();
        }, 'GET', '/v1/transactions/bank'];
        yield 'accounts.listAllTerminals' => [static function (Nomba $n): \Generator {
            return $n->accounts->listAllTerminals(['subAccountId' => 's1']);
        }, 'GET', '/v1/accounts/s1/terminals'];
        yield 'virtualAccounts.listAll' => [static function (Nomba $n): \Generator {
            return $n->virtualAccounts->listAll(['expired' => false]);
        }, 'POST', '/v1/accounts/virtual/list'];
    }

    /**
     * @param \Closure(Nomba): \Generator<int, mixed> $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pagers')]
    public function testEveryPagerWalksPages(\Closure $call, string $method, string $path): void
    {
        $nomba = $this->nomba([
            self::tokenResponse(),
            self::ok(['results' => [['id' => 1]], 'cursor' => 'next']),
            self::ok(['results' => [['id' => 2]]]),
        ]);

        self::assertCount(2, iterator_to_array($call($nomba), false));
        self::assertSame($method, $this->request(2)->getMethod());
        self::assertSame($path, $this->request(2)->getUri()->getPath());
        self::assertSame('next', $this->query(2)['cursor']);
        if ($method === 'POST') {
            self::assertSame($this->body(1), $this->body(2), 'filters are resent on every page');
        }
    }

    public function testStartsFromGivenCursor(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['results' => [['id' => 1]]])]);

        iterator_to_array($nomba->transactions->listAll(['cursor' => 'start']));

        self::assertSame('start', $this->query(1)['cursor']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'too big' => [51];
        yield 'string' => ['10'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badLimits')]
    public function testLimitIsValidated(mixed $limit): void
    {
        $nomba = $this->nomba([]);

        try {
            /** @phpstan-ignore argument.type (deliberately invalid input) */
            $nomba->transactions->list(['limit' => $limit]);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString('limit', $e->getMessage());
            self::assertNull($e->httpStatus);
            self::assertSame(0, $this->requestCount());
        }
    }
}
