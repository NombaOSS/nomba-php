<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Bank and wallet transfers. Transfers are never retried automatically: pass
 * your own merchantTxRef and requery it before retrying (see the README).
 *
 * @phpstan-import-type Bank from \Nomba\Types
 * @phpstan-import-type AccountLookupParams from \Nomba\Types
 * @phpstan-import-type AccountLookup from \Nomba\Types
 * @phpstan-import-type BankTransferParams from \Nomba\Types
 * @phpstan-import-type BankTransfer from \Nomba\Types
 * @phpstan-import-type WalletTransferParams from \Nomba\Types
 * @phpstan-import-type WalletTransfer from \Nomba\Types
 * @phpstan-import-type SubAccountOption from \Nomba\Types
 */
final class Transfers extends AbstractResource
{
    /**
     * GET /v1/transfers/banks
     *
     * @return list<Bank>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listBanks(): array
    {
        /** @var list<Bank> $data */
        $data = $this->requestArray('GET', '/v1/transfers/banks');

        return $data;
    }

    /**
     * POST /v1/transfers/bank/lookup: resolve the account name before sending money.
     *
     * @param AccountLookupParams $params
     *
     * @return AccountLookup
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function lookupAccount(array $params): array
    {
        /** @var AccountLookup $data */
        $data = $this->requestArray('POST', '/v1/transfers/bank/lookup', [], $params);

        return $data;
    }

    /**
     * POST /v2/transfers/bank, or /v2/transfers/bank/{subAccountId}.
     * Generates merchantTxRef if you do not pass one; it is always in the result,
     * and on failure in the exception's getReference().
     *
     * @param BankTransferParams $params
     * @param SubAccountOption   $options
     *
     * @return BankTransfer
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function toBank(array $params, array $options = []): array
    {
        /** @var BankTransfer */
        return $this->postWithReference('/v2/transfers/bank' . Params::subAccountSuffix($options), $params);
    }

    /**
     * POST /v2/transfers/wallet, or /v2/transfers/wallet/{subAccountId}.
     * Generates merchantTxRef if you do not pass one; it is always in the result,
     * and on failure in the exception's getReference().
     *
     * @param WalletTransferParams $params
     * @param SubAccountOption     $options
     *
     * @return WalletTransfer
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function toWallet(array $params, array $options = []): array
    {
        /** @var WalletTransfer */
        return $this->postWithReference('/v2/transfers/wallet' . Params::subAccountSuffix($options), $params);
    }
}
