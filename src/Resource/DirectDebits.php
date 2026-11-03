<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Direct debit mandates.
 *
 * @phpstan-import-type CreateMandateParams from \Nomba\Types
 * @phpstan-import-type CreatedMandate from \Nomba\Types
 * @phpstan-import-type DebitMandateParams from \Nomba\Types
 * @phpstan-import-type MandateDebit from \Nomba\Types
 * @phpstan-import-type MandateStatus from \Nomba\Types
 * @phpstan-import-type ListMandatesParams from \Nomba\Types
 * @phpstan-import-type MandatePage from \Nomba\Types
 * @phpstan-import-type UpdateMandateStatusParams from \Nomba\Types
 * @phpstan-import-type UpdatedMandate from \Nomba\Types
 */
final class DirectDebits extends AbstractResource
{
    /**
     * POST /v1/direct-debits
     *
     * @param CreateMandateParams $params
     *
     * @return CreatedMandate
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function createMandate(array $params): array
    {
        /** @var CreatedMandate $data */
        $data = $this->requestArray('POST', '/v1/direct-debits', [], $params);

        return $data;
    }

    /**
     * POST /v1/direct-debits/debit-mandate
     *
     * @param DebitMandateParams $params
     *
     * @return MandateDebit
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function debitMandate(array $params): array
    {
        /** @var MandateDebit $data */
        $data = $this->requestArray('POST', '/v1/direct-debits/debit-mandate', [], $params);

        return $data;
    }

    /**
     * GET /v1/direct-debits/status?mandateId=
     *
     * @return MandateStatus
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getMandateStatus(string $mandateId): array
    {
        /** @var MandateStatus $data */
        $data = $this->requestArray('GET', '/v1/direct-debits/status', [
            'mandateId' => Params::requireString(['mandateId' => $mandateId], 'mandateId'),
        ]);

        return $data;
    }

    /**
     * GET /v1/direct-debits/mandates (page-numbered).
     *
     * @param ListMandatesParams $params
     *
     * @return MandatePage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listMandates(array $params = []): array
    {
        /** @var MandatePage $data */
        $data = $this->requestArray('GET', '/v1/direct-debits/mandates', Params::only($params, ['page', 'pageSize', 'from', 'to', 'status']));

        return $data;
    }

    /**
     * PUT /v1/direct-debits/update-status: suspend, reactivate or delete a mandate.
     *
     * @param UpdateMandateStatusParams $params
     *
     * @return UpdatedMandate
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function updateMandateStatus(array $params): array
    {
        /** @var UpdatedMandate $data */
        $data = $this->requestArray('PUT', '/v1/direct-debits/update-status', [], $params);

        return $data;
    }
}
