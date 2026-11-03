<?php

declare(strict_types=1);

namespace Nomba;

/**
 * Array shapes for request parameters and response data, as PHPStan/Psalm
 * type aliases. Resources import them with `@phpstan-import-type`.
 *
 * Every shape is open (`...`): Nomba may add fields at any time, and you may
 * send fields the SDK does not list yet. Keys without `?` are required by the
 * API reference.
 *
 * Amounts are `int|float` where the API takes a JSON number and `string` where
 * it returns one as a string.
 *
 * @phpstan-type SubAccountOption array{subAccountId?: string|null}
 * @phpstan-type PageOptions array{limit?: int|null, cursor?: string|null}
 * @phpstan-type BankDetail array{bankAccountNumber?: string, bankName?: string, bankAccountName?: string, ...}
 * @phpstan-type Account array{
 *     createdAt: string,
 *     accountId: string,
 *     accountHolderId: string,
 *     accountRef: string,
 *     bvn?: string,
 *     status: string,
 *     type: string,
 *     accountName: string,
 *     currency: string,
 *     banks?: list<BankDetail>,
 *     ...
 * }
 * @phpstan-type Balance array{amount: string, currency: string, timeCreated: string, ...}
 * @phpstan-type Terminal array{
 *     terminalId: string,
 *     serialNumber: string,
 *     accountId: string,
 *     parentAccountId: string,
 *     merchantName: string,
 *     terminalLabel: string,
 *     createdAt: string,
 *     updatedAt: string,
 *     ...
 * }
 * @phpstan-type TerminalPage array{results?: list<Terminal>, cursor?: string|null, ...}
 * @phpstan-type ListTerminalsParams array{
 *     subAccountId?: string|null,
 *     limit?: int|null,
 *     cursor?: string|null,
 *     terminalId?: string|null,
 *     serialNumber?: string|null,
 *     terminalLabel?: string|null,
 *     merchantName?: string|null,
 * }
 * @phpstan-type CreateVirtualAccountParams array{
 *     accountRef: string,
 *     accountName: string,
 *     bvn?: string,
 *     nin?: string,
 *     expiryDate?: string,
 *     expectedAmount?: int|float,
 *     restrictInflowToAccountName?: bool,
 *     callbackUrl?: string,
 *     ...
 * }
 * @phpstan-type VirtualAccount array{
 *     createdAt: string,
 *     accountHolderId: string,
 *     accountRef: string,
 *     bvn?: string,
 *     identifierType?: string,
 *     accountName: string,
 *     bankName?: string,
 *     bankAccountNumber?: string,
 *     bankAccountName?: string,
 *     currency: string,
 *     callbackUrl?: string,
 *     expired?: bool,
 *     restrictInflowToAccountName?: bool,
 *     ...
 * }
 * @phpstan-type UpdateVirtualAccountParams array{newAccountRef?: string, accountName?: string, expectedAmount?: string|int|float, ...}
 * @phpstan-type VirtualAccountFilters array{
 *     accountName?: string,
 *     accountRef?: string,
 *     bvn?: string,
 *     bankAccountNumber?: string,
 *     dateCreatedFrom?: string,
 *     dateCreatedTo?: string,
 *     expired?: bool,
 *     resourceAcquired?: bool,
 *     ...
 * }
 * @phpstan-type VirtualAccountPage array{results?: list<VirtualAccount>, cursor?: string|null, ...}
 * @phpstan-type SplitRequest array{splitType?: 'PERCENTAGE'|'AMOUNT'|string, splitList?: list<array{accountId?: string, value?: int|float, ...}>, ...}
 * @phpstan-type CheckoutOrderInput array{
 *     orderReference?: string,
 *     customerId?: string,
 *     callbackUrl: string,
 *     customerEmail: string,
 *     amount: int|float,
 *     currency: string,
 *     accountId?: string,
 *     allowedPaymentMethods?: list<string>,
 *     splitRequest?: SplitRequest,
 *     orderMetaData?: array<string, mixed>,
 *     ...
 * }
 * @phpstan-type CreateOrderParams array{order: CheckoutOrderInput, tokenizeCard?: bool, ...}
 * @phpstan-type CreatedOrder array{checkoutLink: string, orderReference: string, ...}
 * @phpstan-type OrderSummary array{
 *     orderId?: string,
 *     orderReference?: string,
 *     customerId?: string,
 *     accountId?: string,
 *     callbackUrl?: string,
 *     customerEmail?: string,
 *     amount?: int|float,
 *     currency?: string,
 *     businessName?: string,
 *     businessEmail?: string,
 *     businessLogo?: string,
 *     ...
 * }
 * @phpstan-type OrderDetails array{order?: OrderSummary, hasSavedCards?: bool, base64EncodedRsaPublicKey?: string, ...}
 * @phpstan-type OrderReferenceParams array{orderReference: string, ...}
 * @phpstan-type SuccessMessage array{success: bool, message: string, ...}
 * @phpstan-type StatusMessage array{status?: bool, message?: string, ...}
 * @phpstan-type CheckoutTransaction array{
 *     success?: bool,
 *     message?: string,
 *     order?: OrderSummary,
 *     transactionDetails?: array{
 *         transactionDate?: string,
 *         paymentReference?: string,
 *         paymentVendorReference?: string,
 *         tokenizedCardPayment?: bool,
 *         statusCode?: string,
 *         status?: string,
 *         ...
 *     },
 *     transferDetails?: array<string, mixed>,
 *     cardDetails?: array{cardPan?: string, cardType?: string, cardCurrency?: string, cardBank?: string, ...},
 *     ...
 * }
 * @phpstan-type RefundParams array{transactionId: string, amount?: int|float, accountNumber?: string, bankCode?: string, ...}
 * @phpstan-type ChargeSavedCardParams array{tokenKey: string, order?: CheckoutOrderInput, ...}
 * @phpstan-type ChargeSavedCardResult array{
 *     status?: bool,
 *     message?: string,
 *     orderId?: string,
 *     orderReference?: string,
 *     otpRequired?: bool,
 *     transactionId?: string,
 *     ...
 * }
 * @phpstan-type SavedCard array{tokenKey?: string, customerEmail?: string, cardType?: string, cardPan?: string, tokenExpiryDate?: string, ...}
 * @phpstan-type ListSavedCardsParams array{customerEmail?: string|null, dateFrom?: string|null, dateTo?: string|null, page?: int|null, limit?: int|null}
 * @phpstan-type SavedCardPage array{hasNextPage?: bool, nextPage?: int, tokenizedCardDataList?: list<SavedCard>, ...}
 * @phpstan-type UpdateSavedCardEmailParams array{tokenKey: string, currentEmailAddress: string, newEmailAddress: string, ...}
 * @phpstan-type UpdatedSavedCard array{status?: bool, message?: string, tokenizedCardData?: SavedCard, ...}
 * @phpstan-type TokenKeyParams array{tokenKey: string, ...}
 * @phpstan-type TransferAccount array{accountNumber: string, accountName: string, bankName: string, ...}
 * @phpstan-type DeviceInformation array{
 *     httpBrowserLanguage?: string,
 *     httpBrowserJavaEnabled?: bool,
 *     httpBrowserJavaScriptEnabled?: bool,
 *     httpBrowserColorDepth?: string,
 *     httpBrowserScreenHeight?: string,
 *     httpBrowserScreenWidth?: string,
 *     httpBrowserTimeDifference?: string,
 *     userAgentBrowserValue?: string,
 *     deviceChannel?: string,
 *     ...
 * }
 * @phpstan-type SubmitCardDetailsParams array{cardDetails?: string, key?: string, orderReference?: string, saveCard?: bool, deviceInformation?: DeviceInformation, ...}
 * @phpstan-type CardDetailsResult array{
 *     status?: bool,
 *     message?: string,
 *     responseCode?: string,
 *     transactionId?: string,
 *     secureAuthenticationData?: array{jwt?: string, md?: string, acsUrl?: string, termUrl?: string, ...},
 *     ...
 * }
 * @phpstan-type SubmitCardOtpParams array{otp: string, orderReference: string, transactionId: string, ...}
 * @phpstan-type OtpResult array{status: bool, message: string, ...}
 * @phpstan-type TransactionReceipt array{status: bool, message: string, order?: OrderSummary, ...}
 * @phpstan-type CancelTransactionParams array{transactionId: string, forceCancel: bool, ...}
 * @phpstan-type RequestSaveCardOtpParams array{orderReference: string, phoneNumber: string, ...}
 * @phpstan-type SubmitUserOtpParams array{orderReference: string, phoneNumber: string, otp: string, ...}
 * @phpstan-type UserSavedCards array{tokenizedCardData?: mixed, ...}
 * @phpstan-type Bank array{code?: string, name?: string, nipCode?: string, logo?: string, ...}
 * @phpstan-type AccountLookupParams array{accountNumber: string, bankCode: string}
 * @phpstan-type AccountLookup array{accountNumber?: string, accountName?: string, ...}
 * @phpstan-type BankTransferParams array{
 *     amount: int|float,
 *     accountNumber: string,
 *     accountName: string,
 *     bankCode: string,
 *     merchantTxRef?: string,
 *     senderName?: string,
 *     narration?: string,
 *     ...
 * }
 * @phpstan-type BankTransfer array{
 *     merchantTxRef: string,
 *     id?: string,
 *     status?: string,
 *     amount?: string|int|float,
 *     fee?: int|float,
 *     type?: string,
 *     timeCreated?: string,
 *     source?: string,
 *     sourceUserId?: string,
 *     customerBillerId?: string,
 *     productId?: string,
 *     meta?: array<string, mixed>,
 *     ...
 * }
 * @phpstan-type WalletTransferParams array{amount: int|float, receiverAccountId: string, merchantTxRef?: string, narration?: string, ...}
 * @phpstan-type WalletTransfer array{
 *     merchantTxRef: string,
 *     id?: string,
 *     status?: string,
 *     amount?: int|float,
 *     fee?: int|float,
 *     type?: string,
 *     timeCreated?: string,
 *     meta?: array<string, mixed>,
 *     ...
 * }
 * @phpstan-type Transaction array{
 *     id: string,
 *     status: string,
 *     amount: int|float,
 *     fixedCharge?: int|float,
 *     source: string,
 *     type: string,
 *     gatewayMessage: string,
 *     customerBillerId?: string,
 *     timeCreated: string,
 *     posTid?: string,
 *     terminalId?: string,
 *     providerTerminalId?: string,
 *     rrn?: string,
 *     posSerialNumber?: string,
 *     posTerminalLabel?: string,
 *     stan?: string,
 *     paymentVendorReference?: string,
 *     userId?: string,
 *     posRrn?: string,
 *     merchantTxRef?: string,
 *     ...
 * }
 * @phpstan-type TransactionPage array{results?: list<Transaction>, cursor?: string|null, ...}
 * @phpstan-type ListTransactionsParams array{subAccountId?: string|null, limit?: int|null, cursor?: string|null, dateFrom?: string|null, dateTo?: string|null}
 * @phpstan-type TransactionFilters array{
 *     transactionRef?: string,
 *     status?: string,
 *     source?: string,
 *     type?: string,
 *     terminalId?: string,
 *     rrn?: string,
 *     merchantTxRef?: string,
 *     orderReference?: string,
 *     orderId?: string,
 *     ...
 * }
 * @phpstan-type GetTransactionParams array{
 *     transactionRef?: string,
 *     merchantTxRef?: string,
 *     orderReference?: string,
 *     orderId?: string,
 *     subAccountId?: string|null,
 * }
 * @phpstan-type BankTransaction array{
 *     amount: int|float,
 *     currency: string,
 *     meta: array<string, mixed>,
 *     status: string,
 *     timeUpdated: string,
 *     walletBalance: int|float,
 *     transactionType: string,
 *     ...
 * }
 * @phpstan-type BankTransactionPage array{results?: list<BankTransaction>, cursor?: string|null, ...}
 * @phpstan-type ListBankTransactionsParams array{limit?: int|null, cursor?: string|null, dateFrom?: string|null, dateTo?: string|null}
 * @phpstan-type AssignTerminalParams array{serialNumber: string, terminalLabel?: string, ...}
 * @phpstan-type PaymentRequestParams array{merchantTxRef: string, amount: int|float, currency: string, ...}
 * @phpstan-type PaymentRequest array{paymentId: string, status: string, amount: int|float, currency: string, createdAt: string, ...}
 * @phpstan-type CreateMandateParams array{
 *     customerAccountNumber: string,
 *     bankCode: string,
 *     customerName: string,
 *     customerAddress?: string,
 *     customerAccountName: string,
 *     amount: int|float,
 *     frequency: string,
 *     narration?: string,
 *     customerPhoneNumber?: string,
 *     merchantReference: string,
 *     startDate: string,
 *     endDate: string,
 *     customerEmail: string,
 *     startImmediately?: bool,
 *     ...
 * }
 * @phpstan-type CreatedMandate array{mandateId: string, merchantReference: string, phoneNumber: string, description: string, ...}
 * @phpstan-type DebitMandateParams array{mandateId: string, amount: string, ...}
 * @phpstan-type MandateDebit array{mandateId?: string, status?: string, amount?: string, message?: string, ...}
 * @phpstan-type MandateStatus array{
 *     customerAccountName: string,
 *     mandateId: string,
 *     customerAccountNumber: string,
 *     mandateStatus: string,
 *     rejectionComment?: string,
 *     mandateAdviceStatus?: string,
 *     ...
 * }
 * @phpstan-type ListMandatesParams array{page?: int|null, pageSize?: int|null, from?: string|null, to?: string|null, status?: string|null}
 * @phpstan-type MandatePage array{
 *     items?: list<array<string, mixed>>,
 *     page?: int,
 *     pageSize?: int,
 *     totalItems?: int,
 *     totalPages?: int,
 *     hasMore?: bool,
 *     ...
 * }
 * @phpstan-type UpdateMandateStatusParams array{mandateId: string, status: 'SUSPEND'|'ACTIVE'|'DELETE'|string, ...}
 * @phpstan-type UpdatedMandate array{mandateId?: string, mandateStatus?: string, ...}
 * @phpstan-type BillPurchase array{
 *     merchantTxRef: string,
 *     id?: string,
 *     status?: string,
 *     type?: string,
 *     amount?: int|float,
 *     source?: string,
 *     sourceUserId?: string,
 *     customerBillerId?: string,
 *     productId?: string,
 *     meta?: array<string, mixed>,
 *     userId?: string,
 *     timeCreated?: string,
 *     ...
 * }
 * @phpstan-type AirtimeParams array{amount: int|float, phoneNumber: string, network: string, merchantTxRef?: string, senderName?: string, ...}
 * @phpstan-type DataParams array{productId: string, amount?: int|float, phoneNumber: string, network: string, merchantTxRef?: string, senderName?: string, ...}
 * @phpstan-type DataPlan array{plan?: string, amount?: int|float, productId?: string, ...}
 * @phpstan-type ElectricityParams array{disco?: string, customerId?: string, amount?: int|float, meterType?: string, payerName?: string, merchantTxRef?: string, ...}
 * @phpstan-type ElectricityProvider array{id?: string, name?: string, ...}
 * @phpstan-type ElectricityCustomer array{name: string, address: string, ...}
 * @phpstan-type CableTvParams array{cableTvType?: string, customerId?: string, amount?: int|float, payerName?: string, merchantTxRef?: string, ...}
 * @phpstan-type CableTvPlan array{subScriptionType: string, amount: int|float, ...}
 * @phpstan-type BettingParams array{bettingProvider?: string, customerId?: string, amount?: int|float, phoneNumber?: string, payerName?: string, merchantTxRef?: string, ...}
 * @phpstan-type BettingProvider array{id: string, name: string, lookup_id?: string, amount?: string, biller_id?: string, ...}
 * @phpstan-type GlobalPayoutAccount array{
 *     accountId?: string,
 *     name?: string,
 *     currency?: string,
 *     balance?: int|float,
 *     availableBalance?: int|float,
 *     status?: string,
 *     operationRegion?: string,
 *     ...
 * }
 * @phpstan-type Provider array{code?: string, displayName?: string, ...}
 * @phpstan-type ListBankProvidersParams array{isMobileMoney?: bool|null, countryIsoCode?: string|null}
 * @phpstan-type ExchangeRatesParams array{from: string, to: string, region?: string|null}
 * @phpstan-type ExchangeRates array{
 *     rates?: list<array{exchangeRateId?: string, currencyPairName?: string, bidRate?: string, askRate?: string, midRate?: string, createdAt?: string, ...}>,
 *     ...
 * }
 * @phpstan-type ListPaymentMethodsParams array{destinationCountryIsoCode?: string|null, destinationCurrency?: string|null, code?: string|null, name?: string|null}
 * @phpstan-type PaymentMethod array{
 *     code?: string,
 *     displayName?: string,
 *     purposeOfPaymentRequired?: bool,
 *     purposeOfPayments?: list<Provider>,
 *     requiredFields?: list<string>,
 *     optionalFields?: list<string>,
 *     accountTypes?: list<string>,
 *     bankAccountTypes?: list<string>,
 *     supportedCorridors?: list<array{countryIsoCode?: string, currency?: string, ...}>,
 *     ...
 * }
 * @phpstan-type GlobalPayoutTransaction array{
 *     transactionId?: string,
 *     status?: string,
 *     coreStatus?: string,
 *     type?: string,
 *     createdAt?: string,
 *     idempotencyKey?: string,
 *     ...
 * }
 * @phpstan-type AuthorizeExchangeParams array{
 *     amount: int|float,
 *     sourceCurrency: string,
 *     destinationCurrency: string,
 *     senderName: string,
 *     receiverName: string,
 *     sourceCountryIsoCode: string,
 *     destinationCountryIsoCode: string,
 *     narration?: string,
 *     lockedExchangeRateId?: string,
 *     idempotencyKey?: string,
 *     ...
 * }
 * @phpstan-type PayoutResult array{
 *     wtTransactionId?: string,
 *     coreTransactionId?: string,
 *     status?: string,
 *     coreStatus?: string,
 *     type?: string,
 *     idempotencyKey?: string,
 *     idempotentReplay?: bool,
 *     prettyStatus?: string,
 *     meta?: array<string, mixed>,
 *     ...
 * }
 * @phpstan-type ConvertMoneyParams array{amount: int|float, currency: string, destinationCurrency: string, transactionType: string, ...}
 * @phpstan-type Conversion array{
 *     fromAmount?: int|float,
 *     fromCurrency?: string,
 *     fromFormatted?: string,
 *     toAmount?: int|float,
 *     toCurrency?: string,
 *     toFormatted?: string,
 *     spreadAmount?: int|float,
 *     spreadCurrency?: string,
 *     exchangeRateId?: string,
 *     currencyPairName?: string,
 *     feeAmount?: int|float,
 *     feeCurrency?: string,
 *     ...
 * }
 * @phpstan-type AuthorizeTransferParams array{
 *     amount: int|float,
 *     sourceCurrency: string,
 *     destinationCurrency: string,
 *     receiverName: string,
 *     sourceCountryIsoCode: string,
 *     destinationCountryIsoCode: string,
 *     paymentMethod: string,
 *     accountType: string,
 *     accountNumber?: string,
 *     institutionCode?: string,
 *     institutionName?: string,
 *     bankAccountType?: string,
 *     purposeOfPayment?: string,
 *     narration?: string,
 *     lockedExchangeRateId?: string,
 *     idempotencyKey?: string,
 *     bankAddress?: string,
 *     bankCity?: string,
 *     bankState?: string,
 *     bankZipCode?: string,
 *     beneficiary?: array<string, string>,
 *     ...
 * }
 * @phpstan-type InitiateInflowParams array{phoneNumber: string, callbackUrl: string, amount: int|float, currency: string, topupVendor: string, idempotencyKey?: string, ...}
 * @phpstan-type InflowResult array{transactionReference?: string, status?: string, message?: string, idempotencyKey?: string, ...}
 * @phpstan-type CollectionTransaction array{
 *     transactionId?: string,
 *     coreUserId?: string,
 *     account?: string,
 *     status?: string,
 *     amount?: int|float,
 *     currency?: string,
 *     vendor?: string,
 *     gatewayMessage?: string,
 *     gatewayCode?: string,
 *     ...
 * }
 * @phpstan-type WebhookEvent array{
 *     event_type: string,
 *     requestId: string,
 *     data: array{merchant: array<string, mixed>, transaction: array<string, mixed>, ...},
 *     ...
 * }
 */
final class Types
{
    /** @codeCoverageIgnore */
    private function __construct() {}
}
