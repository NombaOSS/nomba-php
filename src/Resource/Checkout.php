<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\ApiException;
use Nomba\Exception\ConnectionException;
use Nomba\Internal\Params;

/**
 * Online checkout: hosted orders, saved (tokenized) cards, and the card/OTP
 * steps for building your own checkout page.
 *
 * @phpstan-import-type CancelTransactionParams from \Nomba\Types
 * @phpstan-import-type CardDetailsResult from \Nomba\Types
 * @phpstan-import-type ChargeSavedCardParams from \Nomba\Types
 * @phpstan-import-type ChargeSavedCardResult from \Nomba\Types
 * @phpstan-import-type CheckoutTransaction from \Nomba\Types
 * @phpstan-import-type CreateOrderParams from \Nomba\Types
 * @phpstan-import-type CreatedOrder from \Nomba\Types
 * @phpstan-import-type ListSavedCardsParams from \Nomba\Types
 * @phpstan-import-type OrderDetails from \Nomba\Types
 * @phpstan-import-type OrderReferenceParams from \Nomba\Types
 * @phpstan-import-type OtpResult from \Nomba\Types
 * @phpstan-import-type RefundParams from \Nomba\Types
 * @phpstan-import-type RequestSaveCardOtpParams from \Nomba\Types
 * @phpstan-import-type SavedCardPage from \Nomba\Types
 * @phpstan-import-type StatusMessage from \Nomba\Types
 * @phpstan-import-type SubmitCardDetailsParams from \Nomba\Types
 * @phpstan-import-type SubmitCardOtpParams from \Nomba\Types
 * @phpstan-import-type SubmitUserOtpParams from \Nomba\Types
 * @phpstan-import-type SuccessMessage from \Nomba\Types
 * @phpstan-import-type TokenKeyParams from \Nomba\Types
 * @phpstan-import-type TransactionReceipt from \Nomba\Types
 * @phpstan-import-type TransferAccount from \Nomba\Types
 * @phpstan-import-type UpdateSavedCardEmailParams from \Nomba\Types
 * @phpstan-import-type UpdatedSavedCard from \Nomba\Types
 * @phpstan-import-type UserSavedCards from \Nomba\Types
 */
final class Checkout extends AbstractResource
{
    /**
     * POST /v1/checkout/order: Create a hosted checkout order and get its checkoutLink.
     *
     * @param CreateOrderParams $params
     *
     * @return CreatedOrder
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function createOrder(array $params): array
    {
        /** @var CreatedOrder $data */
        $data = $this->requestArray('POST', '/v1/checkout/order', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/order/cancel: Cancel an unpaid checkout order.
     *
     * @param OrderReferenceParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function cancelOrder(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/order/cancel', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/refund: Refund a checkout transaction (fully, or partly with amount).
     *
     * @param RefundParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function refund(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/refund', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/tokenized-card-data: Change the email linked to a saved card.
     *
     * @param UpdateSavedCardEmailParams $params
     *
     * @return UpdatedSavedCard
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function updateSavedCardEmail(array $params): array
    {
        /** @var UpdatedSavedCard $data */
        $data = $this->requestArray('POST', '/v1/checkout/tokenized-card-data', [], $params);

        return $data;
    }

    /**
     * DELETE /v1/checkout/tokenized-card-data: Delete a saved card. Sends a JSON body with DELETE.
     *
     * @param TokenKeyParams $params
     *
     * @return StatusMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function deleteSavedCard(array $params): array
    {
        /** @var StatusMessage $data */
        $data = $this->requestArray('DELETE', '/v1/checkout/tokenized-card-data', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/checkout-card-detail: Submit encrypted card details for an order.
     *
     * @param SubmitCardDetailsParams $params
     *
     * @return CardDetailsResult
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function submitCardDetails(array $params): array
    {
        /** @var CardDetailsResult $data */
        $data = $this->requestArray('POST', '/v1/checkout/checkout-card-detail', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/checkout-card-otp: Submit the customer's card OTP.
     *
     * @param SubmitCardOtpParams $params
     *
     * @return OtpResult
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function submitCardOtp(array $params): array
    {
        /** @var OtpResult $data */
        $data = $this->requestArray('POST', '/v1/checkout/checkout-card-otp', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/resend-otp: Resend the customer's payment OTP.
     *
     * @param OrderReferenceParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function resendOtp(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/resend-otp', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/confirm-transaction-receipt: Confirm whether an order has been paid.
     *
     * @param OrderReferenceParams $params
     *
     * @return TransactionReceipt
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function confirmTransactionReceipt(array $params): array
    {
        /** @var TransactionReceipt $data */
        $data = $this->requestArray('POST', '/v1/checkout/confirm-transaction-receipt', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/transaction/cancel: Cancel a checkout transaction.
     *
     * @param CancelTransactionParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function cancelTransaction(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/transaction/cancel', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/user-card/auth: Send an OTP to the customer before saving their card.
     *
     * @param RequestSaveCardOtpParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function requestSaveCardOtp(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/user-card/auth', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/user-card/saved-card/auth: Send an OTP to a customer who already has saved cards.
     *
     * @param OrderReferenceParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function requestSavedCardsOtp(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/user-card/saved-card/auth', [], $params);

        return $data;
    }

    /**
     * POST /v1/checkout/user-card: Submit the customer's user OTP.
     *
     * @param SubmitUserOtpParams $params
     *
     * @return SuccessMessage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function submitUserOtp(array $params): array
    {
        /** @var SuccessMessage $data */
        $data = $this->requestArray('POST', '/v1/checkout/user-card', [], $params);

        return $data;
    }

    /**
     * GET /v1/checkout/order/{orderReference}
     *
     * @return OrderDetails
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getOrder(string $orderReference): array
    {
        /** @var OrderDetails $data */
        $data = $this->requestArray('GET', '/v1/checkout/order/' . Params::segment($orderReference, 'orderReference'));

        return $data;
    }

    /**
     * GET /v1/checkout/transaction. idType is for example "ORDER_REFERENCE" or "ORDER_ID".
     *
     * @param array{idType: string, id: string} $params
     *
     * @return CheckoutTransaction
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getTransaction(array $params): array
    {
        $query = [
            'idType' => Params::requireString($params, 'idType'),
            'id' => Params::requireString($params, 'id'),
        ];

        /** @var CheckoutTransaction $data */
        $data = $this->requestArray('GET', '/v1/checkout/transaction', $query);

        return $data;
    }

    /**
     * POST /v1/checkout/tokenized-card-payment: charge a saved card.
     *
     * This endpoint has no merchantTxRef; its reference is order.orderReference.
     * If you pass an order without an orderReference, one is generated (UUID v4),
     * and the result always contains orderReference so you can requery it. If the
     * call throws, the exception's getReference() holds it (field "order.orderReference").
     *
     * @param ChargeSavedCardParams $params
     *
     * @return ChargeSavedCardResult
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function chargeSavedCard(array $params): array
    {
        /** @var ChargeSavedCardResult */
        return $this->postWithReference('/v1/checkout/tokenized-card-payment', $params, 'orderReference', 'order');
    }

    /**
     * GET /v1/checkout/tokenized-card-data: list saved cards (page-numbered).
     *
     * @param ListSavedCardsParams $params
     *
     * @return SavedCardPage
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function listSavedCards(array $params = []): array
    {
        $query = Params::only($params, ['customerEmail', 'dateFrom', 'dateTo', 'page', 'limit']);

        /** @var SavedCardPage $data */
        $data = $this->requestArray('GET', '/v1/checkout/tokenized-card-data', $query);

        return $data;
    }

    /**
     * GET /v1/checkout/get-checkout-kta/{orderReference}: the bank account a
     * customer can transfer to for this order.
     *
     * @return TransferAccount
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getTransferAccount(string $orderReference): array
    {
        /** @var TransferAccount $data */
        $data = $this->requestArray('GET', '/v1/checkout/get-checkout-kta/' . Params::segment($orderReference, 'orderReference'));

        return $data;
    }

    /**
     * GET /v1/checkout/user-card/{orderReference}: the customer's saved cards, after OTP.
     *
     * @param array{otp: string} $params
     *
     * @return UserSavedCards
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function getSavedCards(string $orderReference, array $params): array
    {
        $path = '/v1/checkout/user-card/' . Params::segment($orderReference, 'orderReference');

        /** @var UserSavedCards $data */
        $data = $this->requestArray('GET', $path, ['otp' => Params::requireString($params, 'otp')]);

        return $data;
    }
}
