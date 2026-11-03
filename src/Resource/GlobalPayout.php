<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Cross-border payouts. In the sandbox environment the two account methods use
 * the /v1/sandbox/global-payout/accounts paths.
 *
 * @phpstan-import-type GlobalPayoutAccount from \Nomba\Types
 * @phpstan-import-type Provider from \Nomba\Types
 * @phpstan-import-type ListBankProvidersParams from \Nomba\Types
 * @phpstan-import-type ExchangeRatesParams from \Nomba\Types
 * @phpstan-import-type ExchangeRates from \Nomba\Types
 * @phpstan-import-type ListPaymentMethodsParams from \Nomba\Types
 * @phpstan-import-type PaymentMethod from \Nomba\Types
 * @phpstan-import-type GlobalPayoutTransaction from \Nomba\Types
 * @phpstan-import-type AuthorizeExchangeParams from \Nomba\Types
 * @phpstan-import-type PayoutResult from \Nomba\Types
 * @phpstan-import-type ConvertMoneyParams from \Nomba\Types
 * @phpstan-import-type Conversion from \Nomba\Types
 * @phpstan-import-type AuthorizeTransferParams from \Nomba\Types
 */
final class GlobalPayout extends AbstractResource
{
    /**
     * GET /v1/global-payout/accounts (sandbox: /v1/sandbox/global-payout/accounts)
     *
     * @return list<GlobalPayoutAccount>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listAccounts(): array
    {
        /** @var list<GlobalPayoutAccount> $data */
        $data = $this->requestArray('GET', $this->accountsPath());

        return $data;
    }

    /**
     * GET /v1/global-payout/accounts/{accountId} (sandbox: /v1/sandbox/global-payout/accounts/{accountId})
     *
     * @return GlobalPayoutAccount
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getAccount(string $accountId): array
    {
        /** @var GlobalPayoutAccount $data */
        $data = $this->requestArray('GET', $this->accountsPath() . '/' . Params::segment($accountId, 'accountId'));

        return $data;
    }

    /**
     * GET /v1/global-payout/bank/providers
     *
     * @param ListBankProvidersParams $params
     *
     * @return list<Provider>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listBankProviders(array $params = []): array
    {
        /** @var list<Provider> $data */
        $data = $this->requestArray('GET', '/v1/global-payout/bank/providers', Params::only($params, ['isMobileMoney', 'countryIsoCode']));

        return $data;
    }

    /**
     * GET /v1/global-payout/exchange-rates
     *
     * @param ExchangeRatesParams $params
     *
     * @return ExchangeRates
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getExchangeRates(array $params): array
    {
        $query = [
            'from' => Params::requireString($params, 'from'),
            'to' => Params::requireString($params, 'to'),
            'region' => $params['region'] ?? null,
        ];

        /** @var ExchangeRates $data */
        $data = $this->requestArray('GET', '/v1/global-payout/exchange-rates', $query);

        return $data;
    }

    /**
     * GET /v1/global-payout/payment-methods
     *
     * @param ListPaymentMethodsParams $params
     *
     * @return list<PaymentMethod>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listPaymentMethods(array $params = []): array
    {
        $query = Params::only($params, ['destinationCountryIsoCode', 'destinationCurrency', 'code', 'name']);

        /** @var list<PaymentMethod> $data */
        $data = $this->requestArray('GET', '/v1/global-payout/payment-methods', $query);

        return $data;
    }

    /**
     * GET /v1/global-payout/transactions/{transactionId}
     *
     * @return GlobalPayoutTransaction
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getTransaction(string $transactionId): array
    {
        /** @var GlobalPayoutTransaction $data */
        $data = $this->requestArray('GET', '/v1/global-payout/transactions/' . Params::segment($transactionId, 'transactionId'));

        return $data;
    }

    /**
     * POST /v1/global-payout/exchange/authorize. Pass idempotencyKey to make retries safe.
     *
     * @param AuthorizeExchangeParams $params
     *
     * @return PayoutResult
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function authorizeExchange(array $params): array
    {
        /** @var PayoutResult $data */
        $data = $this->requestArray('POST', '/v1/global-payout/exchange/authorize', [], $params);

        return $data;
    }

    /**
     * POST /v1/global-payout/money/convert: quote a conversion and lock a rate.
     *
     * @param ConvertMoneyParams $params
     *
     * @return Conversion
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function convertMoney(array $params): array
    {
        /** @var Conversion $data */
        $data = $this->requestArray('POST', '/v1/global-payout/money/convert', [], $params);

        return $data;
    }

    /**
     * POST /v1/global-payout/transfer/authorize. Pass idempotencyKey to make retries safe.
     *
     * @param AuthorizeTransferParams $params
     *
     * @return PayoutResult
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function authorizeTransfer(array $params): array
    {
        /** @var PayoutResult $data */
        $data = $this->requestArray('POST', '/v1/global-payout/transfer/authorize', [], $params);

        return $data;
    }

    private function accountsPath(): string
    {
        return $this->client->config->isSandbox()
            ? '/v1/sandbox/global-payout/accounts'
            : '/v1/global-payout/accounts';
    }
}
