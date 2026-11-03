<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * POS terminal assignment and payment requests.
 *
 * @phpstan-import-type AssignTerminalParams from \Nomba\Types
 * @phpstan-import-type Terminal from \Nomba\Types
 * @phpstan-import-type PaymentRequestParams from \Nomba\Types
 * @phpstan-import-type PaymentRequest from \Nomba\Types
 * @phpstan-import-type SubAccountOption from \Nomba\Types
 */
final class Terminals extends AbstractResource
{
    /**
     * POST /v1/terminals/assign, or /v1/terminals/assign/{subAccountId}
     *
     * @param AssignTerminalParams $params
     * @param SubAccountOption     $options
     *
     * @return Terminal
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function assign(array $params, array $options = []): array
    {
        /** @var Terminal $data */
        $data = $this->requestArray('POST', '/v1/terminals/assign' . Params::subAccountSuffix($options), [], $params);

        return $data;
    }

    /**
     * POST /v1/terminals/unassign, or /v1/terminals/unassign/{subAccountId}
     *
     * @param AssignTerminalParams $params
     * @param SubAccountOption     $options
     *
     * @return Terminal
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function unassign(array $params, array $options = []): array
    {
        /** @var Terminal $data */
        $data = $this->requestArray('POST', '/v1/terminals/unassign' . Params::subAccountSuffix($options), [], $params);

        return $data;
    }

    /**
     * POST /v1/terminals/payment-request/{terminalId}: push an amount to a terminal.
     *
     * @param PaymentRequestParams $params
     *
     * @return PaymentRequest
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function sendPaymentRequest(string $terminalId, array $params): array
    {
        /** @var PaymentRequest $data */
        $data = $this->requestArray('POST', '/v1/terminals/payment-request/' . Params::segment($terminalId, 'terminalId'), [], $params);

        return $data;
    }
}
