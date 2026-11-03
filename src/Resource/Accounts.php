<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Parent and sub-account details, balances and terminals.
 *
 * @phpstan-import-type Account from \Nomba\Types
 * @phpstan-import-type Balance from \Nomba\Types
 * @phpstan-import-type SubAccountOption from \Nomba\Types
 * @phpstan-import-type Terminal from \Nomba\Types
 * @phpstan-import-type TerminalPage from \Nomba\Types
 * @phpstan-import-type ListTerminalsParams from \Nomba\Types
 */
final class Accounts extends AbstractResource
{
    /**
     * GET /v1/accounts/parent
     *
     * @return Account
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getParent(): array
    {
        /** @var Account $data */
        $data = $this->requestArray('GET', '/v1/accounts/parent');

        return $data;
    }

    /**
     * GET /v1/accounts/balance, or /v1/accounts/{subAccountId}/balance
     *
     * @param SubAccountOption $params
     *
     * @return Balance
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getBalance(array $params = []): array
    {
        $path = '/v1/accounts' . Params::subAccountSuffix($params) . '/balance';

        /** @var Balance $data */
        $data = $this->requestArray('GET', $path);

        return $data;
    }

    /**
     * GET /v1/accounts/sub-account-details. Pass exactly one of accountId or accountRef.
     *
     * @param array{accountId?: string, accountRef?: string} $params
     *
     * @return Account
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getSubAccount(array $params): array
    {
        $query = Params::exactlyOne($params, ['accountId', 'accountRef']);

        /** @var Account $data */
        $data = $this->requestArray('GET', '/v1/accounts/sub-account-details', $query);

        return $data;
    }

    /**
     * GET /v1/accounts/terminals, or /v1/accounts/{subAccountId}/terminals. One page.
     *
     * @param ListTerminalsParams $params
     *
     * @return TerminalPage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listTerminals(array $params = []): array
    {
        Params::checkLimit($params);
        $path = '/v1/accounts' . Params::subAccountSuffix($params) . '/terminals';
        $query = Params::only($params, ['limit', 'cursor', 'terminalId', 'serialNumber', 'terminalLabel', 'merchantName']);

        /** @var TerminalPage $data */
        $data = $this->requestArray('GET', $path, $query);

        return $data;
    }

    /**
     * Every terminal across all pages.
     *
     * @param ListTerminalsParams $params
     *
     * @return \Generator<int, Terminal, mixed, void>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listAllTerminals(array $params = []): \Generator
    {
        /** @var \Generator<int, Terminal, mixed, void> */
        return self::paginate(
            fn(?string $cursor): array => $this->listTerminals(['cursor' => $cursor] + $params),
            $params['cursor'] ?? null,
        );
    }
}
