<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Cross-border collections (mobile money inflows).
 *
 * @phpstan-import-type Provider from \Nomba\Types
 * @phpstan-import-type InitiateInflowParams from \Nomba\Types
 * @phpstan-import-type InflowResult from \Nomba\Types
 * @phpstan-import-type CollectionTransaction from \Nomba\Types
 */
final class GlobalCollection extends AbstractResource
{
    /**
     * GET /v1/global-collection/drc/inflow/providers
     *
     * @return list<Provider>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listDrcProviders(): array
    {
        /** @var list<Provider> $data */
        $data = $this->requestArray('GET', '/v1/global-collection/drc/inflow/providers');

        return $data;
    }

    /**
     * POST /v1/global-collection/inflow/initiate. Pass idempotencyKey to make retries safe.
     *
     * @param InitiateInflowParams $params
     *
     * @return InflowResult
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function initiateInflow(array $params): array
    {
        /** @var InflowResult $data */
        $data = $this->requestArray('POST', '/v1/global-collection/inflow/initiate', [], $params);

        return $data;
    }

    /**
     * GET /v1/global-collection/transactions/{transactionId}
     *
     * @return CollectionTransaction
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getTransaction(string $transactionId): array
    {
        /** @var CollectionTransaction $data */
        $data = $this->requestArray('GET', '/v1/global-collection/transactions/' . Params::segment($transactionId, 'transactionId'));

        return $data;
    }
}
