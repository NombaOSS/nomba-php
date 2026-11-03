<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Transaction history, lookups and requery.
 *
 * @phpstan-import-type Transaction from \Nomba\Types
 * @phpstan-import-type TransactionPage from \Nomba\Types
 * @phpstan-import-type ListTransactionsParams from \Nomba\Types
 * @phpstan-import-type TransactionFilters from \Nomba\Types
 * @phpstan-import-type GetTransactionParams from \Nomba\Types
 * @phpstan-import-type BankTransaction from \Nomba\Types
 * @phpstan-import-type BankTransactionPage from \Nomba\Types
 * @phpstan-import-type ListBankTransactionsParams from \Nomba\Types
 */
final class Transactions extends AbstractResource
{
    private const PAGE_QUERY = ['limit', 'cursor', 'dateFrom', 'dateTo'];

    /**
     * GET /v1/transactions/accounts, or /v1/transactions/accounts/{subAccountId}. One page.
     *
     * @param ListTransactionsParams $params
     *
     * @return TransactionPage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): array
    {
        Params::checkLimit($params);
        $path = '/v1/transactions/accounts' . Params::subAccountSuffix($params);

        /** @var TransactionPage $data */
        $data = $this->requestArray('GET', $path, Params::only($params, self::PAGE_QUERY));

        return $data;
    }

    /**
     * Every transaction across all pages.
     *
     * @param ListTransactionsParams $params
     *
     * @return \Generator<int, Transaction, mixed, void>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listAll(array $params = []): \Generator
    {
        /** @var \Generator<int, Transaction, mixed, void> */
        return self::paginate(
            fn(?string $cursor): array => $this->list(['cursor' => $cursor] + $params),
            $params['cursor'] ?? null,
        );
    }

    /**
     * POST /v1/transactions/accounts, or /v1/transactions/accounts/{subAccountId}. One page.
     *
     * @param TransactionFilters     $filters
     * @param ListTransactionsParams $options
     *
     * @return TransactionPage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function filter(array $filters = [], array $options = []): array
    {
        Params::checkLimit($options);
        $path = '/v1/transactions/accounts' . Params::subAccountSuffix($options);

        /** @var TransactionPage $data */
        $data = $this->requestArray('POST', $path, Params::only($options, self::PAGE_QUERY), $filters);

        return $data;
    }

    /**
     * Every transaction matching the filters, across all pages.
     *
     * @param TransactionFilters     $filters
     * @param ListTransactionsParams $options
     *
     * @return \Generator<int, Transaction, mixed, void>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function filterAll(array $filters = [], array $options = []): \Generator
    {
        /** @var \Generator<int, Transaction, mixed, void> */
        return self::paginate(
            fn(?string $cursor): array => $this->filter($filters, ['cursor' => $cursor] + $options),
            $options['cursor'] ?? null,
        );
    }

    /**
     * GET /v1/transactions/accounts/single, or /v1/transactions/accounts/{subAccountId}/single.
     * Pass exactly one of transactionRef, merchantTxRef, orderReference or orderId.
     *
     * @param GetTransactionParams $params
     *
     * @return Transaction
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function get(array $params): array
    {
        $query = Params::exactlyOne($params, ['transactionRef', 'merchantTxRef', 'orderReference', 'orderId']);
        $path = '/v1/transactions/accounts' . Params::subAccountSuffix($params) . '/single';

        /** @var Transaction $data */
        $data = $this->requestArray('GET', $path, $query);

        return $data;
    }

    /**
     * GET /v1/transactions/bank: credits and debits on the parent account. One page.
     *
     * @param ListBankTransactionsParams $params
     *
     * @return BankTransactionPage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listBankTransactions(array $params = []): array
    {
        Params::checkLimit($params);

        /** @var BankTransactionPage $data */
        $data = $this->requestArray('GET', '/v1/transactions/bank', Params::only($params, self::PAGE_QUERY));

        return $data;
    }

    /**
     * Every bank transaction across all pages.
     *
     * @param ListBankTransactionsParams $params
     *
     * @return \Generator<int, BankTransaction, mixed, void>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listAllBankTransactions(array $params = []): \Generator
    {
        /** @var \Generator<int, BankTransaction, mixed, void> */
        return self::paginate(
            fn(?string $cursor): array => $this->listBankTransactions(['cursor' => $cursor] + $params),
            $params['cursor'] ?? null,
        );
    }

    /**
     * GET /v1/transactions/requery/{sessionId}: confirm a transfer's final status.
     *
     * @return Transaction
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function requery(string $sessionId): array
    {
        /** @var Transaction $data */
        $data = $this->requestArray('GET', '/v1/transactions/requery/' . Params::segment($sessionId, 'sessionId'));

        return $data;
    }
}
