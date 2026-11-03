<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Virtual (dedicated) bank accounts for collecting payments.
 *
 * @phpstan-import-type CreateVirtualAccountParams from \Nomba\Types
 * @phpstan-import-type VirtualAccount from \Nomba\Types
 * @phpstan-import-type UpdateVirtualAccountParams from \Nomba\Types
 * @phpstan-import-type VirtualAccountFilters from \Nomba\Types
 * @phpstan-import-type VirtualAccountPage from \Nomba\Types
 * @phpstan-import-type SubAccountOption from \Nomba\Types
 * @phpstan-import-type PageOptions from \Nomba\Types
 */
final class VirtualAccounts extends AbstractResource
{
    /**
     * POST /v1/accounts/virtual, or /v1/accounts/virtual/{subAccountId}
     *
     * @param CreateVirtualAccountParams $params
     * @param SubAccountOption           $options
     *
     * @return VirtualAccount
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, array $options = []): array
    {
        /** @var VirtualAccount $data */
        $data = $this->requestArray('POST', '/v1/accounts/virtual' . Params::subAccountSuffix($options), [], $params);

        return $data;
    }

    /**
     * GET /v1/accounts/virtual/{identifier} (accountRef or bank account number)
     *
     * @return VirtualAccount
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function get(string $identifier): array
    {
        /** @var VirtualAccount $data */
        $data = $this->requestArray('GET', '/v1/accounts/virtual/' . Params::segment($identifier, 'identifier'));

        return $data;
    }

    /**
     * PUT /v1/accounts/virtual/{identifier}
     *
     * @param UpdateVirtualAccountParams $params
     *
     * @return array{updated: bool, ...}
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(string $identifier, array $params): array
    {
        /** @var array{updated: bool, ...} $data */
        $data = $this->requestArray('PUT', '/v1/accounts/virtual/' . Params::segment($identifier, 'identifier'), [], $params);

        return $data;
    }

    /**
     * DELETE /v1/accounts/virtual/{identifier}
     *
     * @return array{expired: bool, ...}
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function expire(string $identifier): array
    {
        /** @var array{expired: bool, ...} $data */
        $data = $this->requestArray('DELETE', '/v1/accounts/virtual/' . Params::segment($identifier, 'identifier'));

        return $data;
    }

    /**
     * POST /v1/accounts/virtual/list. One page.
     *
     * @param VirtualAccountFilters $filters
     * @param PageOptions           $options
     *
     * @return VirtualAccountPage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $filters = [], array $options = []): array
    {
        Params::checkLimit($options);

        /** @var VirtualAccountPage $data */
        $data = $this->requestArray('POST', '/v1/accounts/virtual/list', Params::only($options, ['limit', 'cursor']), $filters);

        return $data;
    }

    /**
     * Every virtual account matching the filters, across all pages.
     *
     * @param VirtualAccountFilters $filters
     * @param PageOptions           $options
     *
     * @return \Generator<int, VirtualAccount, mixed, void>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listAll(array $filters = [], array $options = []): \Generator
    {
        /** @var \Generator<int, VirtualAccount, mixed, void> */
        return self::paginate(
            fn(?string $cursor): array => $this->list($filters, ['cursor' => $cursor] + $options),
            $options['cursor'] ?? null,
        );
    }
}
