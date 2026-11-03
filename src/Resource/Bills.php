<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Airtime, data, electricity, cable TV and betting. Purchases use the v2
 * endpoints, generate merchantTxRef when you do not pass one, and are never
 * retried automatically.
 *
 * @phpstan-import-type BillPurchase from \Nomba\Types
 * @phpstan-import-type AirtimeParams from \Nomba\Types
 * @phpstan-import-type DataParams from \Nomba\Types
 * @phpstan-import-type DataPlan from \Nomba\Types
 * @phpstan-import-type ElectricityParams from \Nomba\Types
 * @phpstan-import-type ElectricityProvider from \Nomba\Types
 * @phpstan-import-type ElectricityCustomer from \Nomba\Types
 * @phpstan-import-type CableTvParams from \Nomba\Types
 * @phpstan-import-type CableTvPlan from \Nomba\Types
 * @phpstan-import-type BettingParams from \Nomba\Types
 * @phpstan-import-type BettingProvider from \Nomba\Types
 * @phpstan-import-type SubAccountOption from \Nomba\Types
 */
final class Bills extends AbstractResource
{
    /**
     * POST /v2/bill/topup[/{subAccountId}]
     *
     * @param AirtimeParams    $params
     * @param SubAccountOption $options
     *
     * @return BillPurchase
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function buyAirtime(array $params, array $options = []): array
    {
        return $this->purchase('/v2/bill/topup', $params, $options);
    }

    /**
     * POST /v2/bill/data[/{subAccountId}]
     *
     * @param DataParams       $params
     * @param SubAccountOption $options
     *
     * @return BillPurchase
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function buyData(array $params, array $options = []): array
    {
        return $this->purchase('/v2/bill/data', $params, $options);
    }

    /**
     * GET /v1/bill/data-plan/{telco}. The telco name is lowercased (mtn, glo, airtel, 9mobile).
     *
     * @return list<DataPlan>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listDataPlans(string $telco): array
    {
        /** @var list<DataPlan> $data */
        $data = $this->requestArray('GET', '/v1/bill/data-plan/' . Params::segment(strtolower($telco), 'telco'));

        return $data;
    }

    /**
     * POST /v2/bill/electricity[/{subAccountId}]
     *
     * @param ElectricityParams $params
     * @param SubAccountOption  $options
     *
     * @return BillPurchase
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function buyElectricity(array $params, array $options = []): array
    {
        return $this->purchase('/v2/bill/electricity', $params, $options);
    }

    /**
     * GET /v1/bill/electricity/discos
     *
     * @return list<ElectricityProvider>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listElectricityProviders(): array
    {
        /** @var list<ElectricityProvider> $data */
        $data = $this->requestArray('GET', '/v1/bill/electricity/discos');

        return $data;
    }

    /**
     * GET /v2/bill/electricity/lookup
     *
     * @param array{disco: string, customerId: string} $params
     *
     * @return ElectricityCustomer
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function lookupElectricityCustomer(array $params): array
    {
        /** @var ElectricityCustomer $data */
        $data = $this->requestArray('GET', '/v2/bill/electricity/lookup', [
            'disco' => Params::requireString($params, 'disco'),
            'customerId' => Params::requireString($params, 'customerId'),
        ]);

        return $data;
    }

    /**
     * POST /v2/bill/cabletv[/{subAccountId}]
     *
     * @param CableTvParams    $params
     * @param SubAccountOption $options
     *
     * @return BillPurchase
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function payCableTv(array $params, array $options = []): array
    {
        return $this->purchase('/v2/bill/cabletv', $params, $options);
    }

    /**
     * GET /v1/bill/cableTvProduct?cableTvType= (for example DSTV, GOTV, STARTIMES)
     *
     * @return list<CableTvPlan>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listCableTvPlans(string $cableTvType): array
    {
        /** @var list<CableTvPlan> $data */
        $data = $this->requestArray('GET', '/v1/bill/cableTvProduct', [
            'cableTvType' => Params::requireString(['cableTvType' => $cableTvType], 'cableTvType'),
        ]);

        return $data;
    }

    /**
     * GET /v1/bill/cabletv/lookup. Returns the customer name.
     *
     * @param array{customerId: string, cableTvType: string} $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function lookupCableTvCustomer(array $params): string
    {
        $data = $this->client->request('GET', '/v1/bill/cabletv/lookup', [
            'customerId' => Params::requireString($params, 'customerId'),
            'cableTvType' => Params::requireString($params, 'cableTvType'),
        ]);

        return \is_scalar($data) ? (string) $data : '';
    }

    /**
     * POST /v2/bill/betting[/{subAccountId}]
     *
     * @param BettingParams    $params
     * @param SubAccountOption $options
     *
     * @return BillPurchase
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function fundBetting(array $params, array $options = []): array
    {
        return $this->purchase('/v2/bill/betting', $params, $options);
    }

    /**
     * GET /v1/bill/betting/providers
     *
     * @return list<BettingProvider>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listBettingProviders(): array
    {
        /** @var list<BettingProvider> $data */
        $data = $this->requestArray('GET', '/v1/bill/betting/providers');

        return $data;
    }

    /**
     * GET /v1/bill/betting/lookup. Returns the customer name.
     *
     * @param array{providerId: string, customerId: string} $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function lookupBettingCustomer(array $params): string
    {
        $data = $this->client->request('GET', '/v1/bill/betting/lookup', [
            'providerId' => Params::requireString($params, 'providerId'),
            'customerId' => Params::requireString($params, 'customerId'),
        ]);

        return \is_scalar($data) ? (string) $data : '';
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     *
     * @return BillPurchase
     */
    private function purchase(string $path, array $params, array $options): array
    {
        $path .= Params::subAccountSuffix($options);
        [$params, $ref] = Params::withReference($params);

        /** @var BillPurchase */
        return Params::attachReference($this->client->request('POST', $path, [], $params), $ref);
    }
}
