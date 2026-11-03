<?php

declare(strict_types=1);

namespace Nomba\Tests;

use Nomba\Nomba;

/**
 * One request test per method: HTTP method, path, query, headers and body.
 */
final class ResourcesTest extends TestCase
{
    private const SUB = 'sub-0001';

    /**
     * @return iterable<string, array{\Closure(Nomba): mixed, string, string, array<string, string>, array<mixed>|null}>
     */
    public static function calls(): iterable
    {
        $order = ['callbackUrl' => 'https://example.com/cb', 'customerEmail' => 'c@example.com', 'amount' => 1000.5, 'currency' => 'NGN', 'orderReference' => 'ord-1'];

        // accounts
        yield 'accounts.getParent' => [static fn(Nomba $n) => $n->accounts->getParent(), 'GET', '/v1/accounts/parent', [], null];
        yield 'accounts.getBalance' => [static fn(Nomba $n) => $n->accounts->getBalance(), 'GET', '/v1/accounts/balance', [], null];
        yield 'accounts.getBalance sub' => [static fn(Nomba $n) => $n->accounts->getBalance(['subAccountId' => self::SUB]), 'GET', '/v1/accounts/sub-0001/balance', [], null];
        yield 'accounts.getSubAccount' => [static fn(Nomba $n) => $n->accounts->getSubAccount(['accountRef' => 'r-1']), 'GET', '/v1/accounts/sub-account-details', ['accountRef' => 'r-1'], null];
        yield 'accounts.listTerminals' => [static fn(Nomba $n) => $n->accounts->listTerminals(['limit' => 10, 'serialNumber' => 'SN1', 'merchantName' => null]), 'GET', '/v1/accounts/terminals', ['limit' => '10', 'serialNumber' => 'SN1'], null];
        yield 'accounts.listTerminals sub' => [static fn(Nomba $n) => $n->accounts->listTerminals(['subAccountId' => self::SUB, 'cursor' => 'c1']), 'GET', '/v1/accounts/sub-0001/terminals', ['cursor' => 'c1'], null];

        // virtualAccounts
        $va = ['accountRef' => 'va-1', 'accountName' => 'Jane Doe', 'expectedAmount' => 500];
        yield 'virtualAccounts.create' => [static fn(Nomba $n) => $n->virtualAccounts->create($va), 'POST', '/v1/accounts/virtual', [], $va];
        yield 'virtualAccounts.create sub' => [static fn(Nomba $n) => $n->virtualAccounts->create($va, ['subAccountId' => self::SUB]), 'POST', '/v1/accounts/virtual/sub-0001', [], $va];
        yield 'virtualAccounts.get' => [static fn(Nomba $n) => $n->virtualAccounts->get('va 1'), 'GET', '/v1/accounts/virtual/va%201', [], null];
        yield 'virtualAccounts.update' => [static fn(Nomba $n) => $n->virtualAccounts->update('va-1', ['accountName' => 'New']), 'PUT', '/v1/accounts/virtual/va-1', [], ['accountName' => 'New']];
        yield 'virtualAccounts.expire' => [static fn(Nomba $n) => $n->virtualAccounts->expire('va-1'), 'DELETE', '/v1/accounts/virtual/va-1', [], null];
        yield 'virtualAccounts.list' => [static fn(Nomba $n) => $n->virtualAccounts->list(['expired' => false], ['limit' => 50]), 'POST', '/v1/accounts/virtual/list', ['limit' => '50'], ['expired' => false]];
        yield 'virtualAccounts.list empty' => [static fn(Nomba $n) => $n->virtualAccounts->list(), 'POST', '/v1/accounts/virtual/list', [], []];

        // checkout
        yield 'checkout.createOrder' => [static fn(Nomba $n) => $n->checkout->createOrder(['order' => $order, 'tokenizeCard' => true]), 'POST', '/v1/checkout/order', [], ['order' => $order, 'tokenizeCard' => true]];
        yield 'checkout.getOrder' => [static fn(Nomba $n) => $n->checkout->getOrder('ord-1'), 'GET', '/v1/checkout/order/ord-1', [], null];
        yield 'checkout.cancelOrder' => [static fn(Nomba $n) => $n->checkout->cancelOrder(['orderReference' => 'ord-1']), 'POST', '/v1/checkout/order/cancel', [], ['orderReference' => 'ord-1']];
        yield 'checkout.getTransaction' => [static fn(Nomba $n) => $n->checkout->getTransaction(['idType' => 'ORDER_REFERENCE', 'id' => 'ord-1']), 'GET', '/v1/checkout/transaction', ['idType' => 'ORDER_REFERENCE', 'id' => 'ord-1'], null];
        yield 'checkout.refund' => [static fn(Nomba $n) => $n->checkout->refund(['transactionId' => 't-1', 'amount' => 50]), 'POST', '/v1/checkout/refund', [], ['transactionId' => 't-1', 'amount' => 50]];
        yield 'checkout.chargeSavedCard' => [static fn(Nomba $n) => $n->checkout->chargeSavedCard(['tokenKey' => 'tk-1', 'order' => $order]), 'POST', '/v1/checkout/tokenized-card-payment', [], ['tokenKey' => 'tk-1', 'order' => $order]];
        yield 'checkout.listSavedCards' => [static fn(Nomba $n) => $n->checkout->listSavedCards(['customerEmail' => 'c@example.com', 'page' => 1, 'limit' => 20]), 'GET', '/v1/checkout/tokenized-card-data', ['customerEmail' => 'c@example.com', 'page' => '1', 'limit' => '20'], null];
        yield 'checkout.updateSavedCardEmail' => [static fn(Nomba $n) => $n->checkout->updateSavedCardEmail(['tokenKey' => 'tk', 'currentEmailAddress' => 'a@x.com', 'newEmailAddress' => 'b@x.com']), 'POST', '/v1/checkout/tokenized-card-data', [], ['tokenKey' => 'tk', 'currentEmailAddress' => 'a@x.com', 'newEmailAddress' => 'b@x.com']];
        yield 'checkout.deleteSavedCard' => [static fn(Nomba $n) => $n->checkout->deleteSavedCard(['tokenKey' => 'tk']), 'DELETE', '/v1/checkout/tokenized-card-data', [], ['tokenKey' => 'tk']];
        yield 'checkout.getTransferAccount' => [static fn(Nomba $n) => $n->checkout->getTransferAccount('ord-1'), 'GET', '/v1/checkout/get-checkout-kta/ord-1', [], null];
        yield 'checkout.submitCardDetails' => [static fn(Nomba $n) => $n->checkout->submitCardDetails(['cardDetails' => 'enc', 'key' => 'k', 'orderReference' => 'ord-1', 'saveCard' => false]), 'POST', '/v1/checkout/checkout-card-detail', [], ['cardDetails' => 'enc', 'key' => 'k', 'orderReference' => 'ord-1', 'saveCard' => false]];
        yield 'checkout.submitCardOtp' => [static fn(Nomba $n) => $n->checkout->submitCardOtp(['otp' => '123456', 'orderReference' => 'ord-1', 'transactionId' => 't-1']), 'POST', '/v1/checkout/checkout-card-otp', [], ['otp' => '123456', 'orderReference' => 'ord-1', 'transactionId' => 't-1']];
        yield 'checkout.resendOtp' => [static fn(Nomba $n) => $n->checkout->resendOtp(['orderReference' => 'ord-1']), 'POST', '/v1/checkout/resend-otp', [], ['orderReference' => 'ord-1']];
        yield 'checkout.confirmTransactionReceipt' => [static fn(Nomba $n) => $n->checkout->confirmTransactionReceipt(['orderReference' => 'ord-1']), 'POST', '/v1/checkout/confirm-transaction-receipt', [], ['orderReference' => 'ord-1']];
        yield 'checkout.cancelTransaction' => [static fn(Nomba $n) => $n->checkout->cancelTransaction(['transactionId' => 't-1', 'forceCancel' => true]), 'POST', '/v1/checkout/transaction/cancel', [], ['transactionId' => 't-1', 'forceCancel' => true]];
        yield 'checkout.requestSaveCardOtp' => [static fn(Nomba $n) => $n->checkout->requestSaveCardOtp(['orderReference' => 'ord-1', 'phoneNumber' => '08000000000']), 'POST', '/v1/checkout/user-card/auth', [], ['orderReference' => 'ord-1', 'phoneNumber' => '08000000000']];
        yield 'checkout.requestSavedCardsOtp' => [static fn(Nomba $n) => $n->checkout->requestSavedCardsOtp(['orderReference' => 'ord-1']), 'POST', '/v1/checkout/user-card/saved-card/auth', [], ['orderReference' => 'ord-1']];
        yield 'checkout.submitUserOtp' => [static fn(Nomba $n) => $n->checkout->submitUserOtp(['orderReference' => 'ord-1', 'phoneNumber' => '0800', 'otp' => '1111']), 'POST', '/v1/checkout/user-card', [], ['orderReference' => 'ord-1', 'phoneNumber' => '0800', 'otp' => '1111']];
        yield 'checkout.getSavedCards' => [static fn(Nomba $n) => $n->checkout->getSavedCards('ord-1', ['otp' => '1111']), 'GET', '/v1/checkout/user-card/ord-1', ['otp' => '1111'], null];

        // transfers
        $bank = ['amount' => 2500, 'accountNumber' => '0123456789', 'accountName' => 'Jane Doe', 'bankCode' => '058', 'merchantTxRef' => 'tx-1', 'narration' => 'Rent'];
        $wallet = ['amount' => 100, 'receiverAccountId' => 'acct-2', 'merchantTxRef' => 'tx-2'];
        yield 'transfers.listBanks' => [static fn(Nomba $n) => $n->transfers->listBanks(), 'GET', '/v1/transfers/banks', [], null];
        yield 'transfers.lookupAccount' => [static fn(Nomba $n) => $n->transfers->lookupAccount(['accountNumber' => '0123456789', 'bankCode' => '058']), 'POST', '/v1/transfers/bank/lookup', [], ['accountNumber' => '0123456789', 'bankCode' => '058']];
        yield 'transfers.toBank' => [static fn(Nomba $n) => $n->transfers->toBank($bank), 'POST', '/v2/transfers/bank', [], $bank];
        yield 'transfers.toBank sub' => [static fn(Nomba $n) => $n->transfers->toBank($bank, ['subAccountId' => self::SUB]), 'POST', '/v2/transfers/bank/sub-0001', [], $bank];
        yield 'transfers.toWallet' => [static fn(Nomba $n) => $n->transfers->toWallet($wallet), 'POST', '/v2/transfers/wallet', [], $wallet];
        yield 'transfers.toWallet sub' => [static fn(Nomba $n) => $n->transfers->toWallet($wallet, ['subAccountId' => self::SUB]), 'POST', '/v2/transfers/wallet/sub-0001', [], $wallet];

        // transactions
        yield 'transactions.list' => [static fn(Nomba $n) => $n->transactions->list(['limit' => 5, 'dateFrom' => '2026-01-01', 'dateTo' => '2026-01-31']), 'GET', '/v1/transactions/accounts', ['limit' => '5', 'dateFrom' => '2026-01-01', 'dateTo' => '2026-01-31'], null];
        yield 'transactions.list sub' => [static fn(Nomba $n) => $n->transactions->list(['subAccountId' => self::SUB]), 'GET', '/v1/transactions/accounts/sub-0001', [], null];
        yield 'transactions.filter' => [static fn(Nomba $n) => $n->transactions->filter(['status' => 'SUCCESS'], ['limit' => 20]), 'POST', '/v1/transactions/accounts', ['limit' => '20'], ['status' => 'SUCCESS']];
        yield 'transactions.filter sub' => [static fn(Nomba $n) => $n->transactions->filter(['type' => 'transfer'], ['subAccountId' => self::SUB, 'cursor' => 'c']), 'POST', '/v1/transactions/accounts/sub-0001', ['cursor' => 'c'], ['type' => 'transfer']];
        yield 'transactions.get' => [static fn(Nomba $n) => $n->transactions->get(['merchantTxRef' => 'tx-1']), 'GET', '/v1/transactions/accounts/single', ['merchantTxRef' => 'tx-1'], null];
        yield 'transactions.get sub' => [static fn(Nomba $n) => $n->transactions->get(['orderId' => 'o-1', 'subAccountId' => self::SUB]), 'GET', '/v1/transactions/accounts/sub-0001/single', ['orderId' => 'o-1'], null];
        yield 'transactions.listBankTransactions' => [static fn(Nomba $n) => $n->transactions->listBankTransactions(['limit' => 1]), 'GET', '/v1/transactions/bank', ['limit' => '1'], null];
        yield 'transactions.requery' => [static fn(Nomba $n) => $n->transactions->requery('100004250929105143781234567890'), 'GET', '/v1/transactions/requery/100004250929105143781234567890', [], null];

        // terminals
        yield 'terminals.assign' => [static fn(Nomba $n) => $n->terminals->assign(['serialNumber' => 'SN1', 'terminalLabel' => 'Till 1']), 'POST', '/v1/terminals/assign', [], ['serialNumber' => 'SN1', 'terminalLabel' => 'Till 1']];
        yield 'terminals.assign sub' => [static fn(Nomba $n) => $n->terminals->assign(['serialNumber' => 'SN1'], ['subAccountId' => self::SUB]), 'POST', '/v1/terminals/assign/sub-0001', [], ['serialNumber' => 'SN1']];
        yield 'terminals.unassign' => [static fn(Nomba $n) => $n->terminals->unassign(['serialNumber' => 'SN1']), 'POST', '/v1/terminals/unassign', [], ['serialNumber' => 'SN1']];
        yield 'terminals.unassign sub' => [static fn(Nomba $n) => $n->terminals->unassign(['serialNumber' => 'SN1'], ['subAccountId' => self::SUB]), 'POST', '/v1/terminals/unassign/sub-0001', [], ['serialNumber' => 'SN1']];
        yield 'terminals.sendPaymentRequest' => [static fn(Nomba $n) => $n->terminals->sendPaymentRequest('T-1', ['merchantTxRef' => 'p-1', 'amount' => 10, 'currency' => 'NGN']), 'POST', '/v1/terminals/payment-request/T-1', [], ['merchantTxRef' => 'p-1', 'amount' => 10, 'currency' => 'NGN']];

        // directDebits
        $mandate = ['customerAccountNumber' => '0123456789', 'bankCode' => '058', 'customerName' => 'Jane', 'customerAccountName' => 'Jane Doe', 'amount' => 1000, 'frequency' => 'MONTHLY', 'merchantReference' => 'm-1', 'startDate' => '2026-01-01T00:00:00Z', 'endDate' => '2026-12-31T00:00:00Z', 'customerEmail' => 'j@example.com'];
        yield 'directDebits.createMandate' => [static fn(Nomba $n) => $n->directDebits->createMandate($mandate), 'POST', '/v1/direct-debits', [], $mandate];
        yield 'directDebits.debitMandate' => [static fn(Nomba $n) => $n->directDebits->debitMandate(['mandateId' => 'md-1', 'amount' => '500']), 'POST', '/v1/direct-debits/debit-mandate', [], ['mandateId' => 'md-1', 'amount' => '500']];
        yield 'directDebits.getMandateStatus' => [static fn(Nomba $n) => $n->directDebits->getMandateStatus('md-1'), 'GET', '/v1/direct-debits/status', ['mandateId' => 'md-1'], null];
        yield 'directDebits.listMandates' => [static fn(Nomba $n) => $n->directDebits->listMandates(['page' => 1, 'pageSize' => 10, 'status' => 'ACTIVE']), 'GET', '/v1/direct-debits/mandates', ['page' => '1', 'pageSize' => '10', 'status' => 'ACTIVE'], null];
        yield 'directDebits.updateMandateStatus' => [static fn(Nomba $n) => $n->directDebits->updateMandateStatus(['mandateId' => 'md-1', 'status' => 'SUSPEND']), 'PUT', '/v1/direct-debits/update-status', [], ['mandateId' => 'md-1', 'status' => 'SUSPEND']];

        // bills
        $airtime = ['amount' => 100, 'phoneNumber' => '08000000000', 'network' => 'MTN', 'merchantTxRef' => 'b-1'];
        $data = ['productId' => 'p-1', 'phoneNumber' => '08000000000', 'network' => 'GLO', 'merchantTxRef' => 'b-2'];
        $power = ['disco' => 'IKEDC', 'customerId' => '45700000000', 'amount' => 2000, 'meterType' => 'PREPAID', 'merchantTxRef' => 'b-3'];
        $cable = ['cableTvType' => 'DSTV', 'customerId' => '7000000000', 'amount' => 5000, 'merchantTxRef' => 'b-4'];
        $bet = ['bettingProvider' => 'BET9JA', 'customerId' => 'c-1', 'amount' => 500, 'merchantTxRef' => 'b-5'];
        yield 'bills.buyAirtime' => [static fn(Nomba $n) => $n->bills->buyAirtime($airtime), 'POST', '/v2/bill/topup', [], $airtime];
        yield 'bills.buyAirtime sub' => [static fn(Nomba $n) => $n->bills->buyAirtime($airtime, ['subAccountId' => self::SUB]), 'POST', '/v2/bill/topup/sub-0001', [], $airtime];
        yield 'bills.buyData' => [static fn(Nomba $n) => $n->bills->buyData($data), 'POST', '/v2/bill/data', [], $data];
        yield 'bills.buyData sub' => [static fn(Nomba $n) => $n->bills->buyData($data, ['subAccountId' => self::SUB]), 'POST', '/v2/bill/data/sub-0001', [], $data];
        yield 'bills.listDataPlans' => [static fn(Nomba $n) => $n->bills->listDataPlans('MTN'), 'GET', '/v1/bill/data-plan/mtn', [], null];
        yield 'bills.buyElectricity' => [static fn(Nomba $n) => $n->bills->buyElectricity($power), 'POST', '/v2/bill/electricity', [], $power];
        yield 'bills.buyElectricity sub' => [static fn(Nomba $n) => $n->bills->buyElectricity($power, ['subAccountId' => self::SUB]), 'POST', '/v2/bill/electricity/sub-0001', [], $power];
        yield 'bills.listElectricityProviders' => [static fn(Nomba $n) => $n->bills->listElectricityProviders(), 'GET', '/v1/bill/electricity/discos', [], null];
        yield 'bills.lookupElectricityCustomer' => [static fn(Nomba $n) => $n->bills->lookupElectricityCustomer(['disco' => 'IKEDC', 'customerId' => '457']), 'GET', '/v2/bill/electricity/lookup', ['disco' => 'IKEDC', 'customerId' => '457'], null];
        yield 'bills.payCableTv' => [static fn(Nomba $n) => $n->bills->payCableTv($cable), 'POST', '/v2/bill/cabletv', [], $cable];
        yield 'bills.payCableTv sub' => [static fn(Nomba $n) => $n->bills->payCableTv($cable, ['subAccountId' => self::SUB]), 'POST', '/v2/bill/cabletv/sub-0001', [], $cable];
        yield 'bills.listCableTvPlans' => [static fn(Nomba $n) => $n->bills->listCableTvPlans('DSTV'), 'GET', '/v1/bill/cableTvProduct', ['cableTvType' => 'DSTV'], null];
        yield 'bills.lookupCableTvCustomer' => [static fn(Nomba $n) => $n->bills->lookupCableTvCustomer(['customerId' => '7000', 'cableTvType' => 'GOTV']), 'GET', '/v1/bill/cabletv/lookup', ['customerId' => '7000', 'cableTvType' => 'GOTV'], null];
        yield 'bills.fundBetting' => [static fn(Nomba $n) => $n->bills->fundBetting($bet), 'POST', '/v2/bill/betting', [], $bet];
        yield 'bills.fundBetting sub' => [static fn(Nomba $n) => $n->bills->fundBetting($bet, ['subAccountId' => self::SUB]), 'POST', '/v2/bill/betting/sub-0001', [], $bet];
        yield 'bills.listBettingProviders' => [static fn(Nomba $n) => $n->bills->listBettingProviders(), 'GET', '/v1/bill/betting/providers', [], null];
        yield 'bills.lookupBettingCustomer' => [static fn(Nomba $n) => $n->bills->lookupBettingCustomer(['providerId' => 'BET9JA', 'customerId' => 'c-1']), 'GET', '/v1/bill/betting/lookup', ['providerId' => 'BET9JA', 'customerId' => 'c-1'], null];

        // globalPayout (sandbox paths for accounts)
        $exchange = ['amount' => 100, 'sourceCurrency' => 'NGN', 'destinationCurrency' => 'USD', 'senderName' => 'A', 'receiverName' => 'B', 'sourceCountryIsoCode' => 'NG', 'destinationCountryIsoCode' => 'US', 'idempotencyKey' => 'i-1'];
        $payout = ['amount' => 100, 'sourceCurrency' => 'NGN', 'destinationCurrency' => 'GBP', 'receiverName' => 'B', 'sourceCountryIsoCode' => 'NG', 'destinationCountryIsoCode' => 'GB', 'paymentMethod' => 'FASTER_PAYMENTS', 'accountType' => 'INDIVIDUAL'];
        yield 'globalPayout.listAccounts' => [static fn(Nomba $n) => $n->globalPayout->listAccounts(), 'GET', '/v1/sandbox/global-payout/accounts', [], null];
        yield 'globalPayout.getAccount' => [static fn(Nomba $n) => $n->globalPayout->getAccount('gp-1'), 'GET', '/v1/sandbox/global-payout/accounts/gp-1', [], null];
        yield 'globalPayout.listBankProviders' => [static fn(Nomba $n) => $n->globalPayout->listBankProviders(['isMobileMoney' => true, 'countryIsoCode' => 'CD']), 'GET', '/v1/global-payout/bank/providers', ['isMobileMoney' => 'true', 'countryIsoCode' => 'CD'], null];
        yield 'globalPayout.getExchangeRates' => [static fn(Nomba $n) => $n->globalPayout->getExchangeRates(['from' => 'NGN', 'to' => 'USD']), 'GET', '/v1/global-payout/exchange-rates', ['from' => 'NGN', 'to' => 'USD'], null];
        yield 'globalPayout.listPaymentMethods' => [static fn(Nomba $n) => $n->globalPayout->listPaymentMethods(['destinationCountryIsoCode' => 'GB']), 'GET', '/v1/global-payout/payment-methods', ['destinationCountryIsoCode' => 'GB'], null];
        yield 'globalPayout.getTransaction' => [static fn(Nomba $n) => $n->globalPayout->getTransaction('wt-1'), 'GET', '/v1/global-payout/transactions/wt-1', [], null];
        yield 'globalPayout.authorizeExchange' => [static fn(Nomba $n) => $n->globalPayout->authorizeExchange($exchange), 'POST', '/v1/global-payout/exchange/authorize', [], $exchange];
        yield 'globalPayout.convertMoney' => [static fn(Nomba $n) => $n->globalPayout->convertMoney(['amount' => 1, 'currency' => 'NGN', 'destinationCurrency' => 'USD', 'transactionType' => 'SELL']), 'POST', '/v1/global-payout/money/convert', [], ['amount' => 1, 'currency' => 'NGN', 'destinationCurrency' => 'USD', 'transactionType' => 'SELL']];
        yield 'globalPayout.authorizeTransfer' => [static fn(Nomba $n) => $n->globalPayout->authorizeTransfer($payout), 'POST', '/v1/global-payout/transfer/authorize', [], $payout];

        // globalCollection
        $inflow = ['phoneNumber' => '243000000000', 'callbackUrl' => 'https://example.com/cb', 'amount' => 10, 'currency' => 'CDF', 'topupVendor' => 'ORANGE'];
        yield 'globalCollection.listDrcProviders' => [static fn(Nomba $n) => $n->globalCollection->listDrcProviders(), 'GET', '/v1/global-collection/drc/inflow/providers', [], null];
        yield 'globalCollection.initiateInflow' => [static fn(Nomba $n) => $n->globalCollection->initiateInflow($inflow), 'POST', '/v1/global-collection/inflow/initiate', [], $inflow];
        yield 'globalCollection.getTransaction' => [static fn(Nomba $n) => $n->globalCollection->getTransaction('gc-1'), 'GET', '/v1/global-collection/transactions/gc-1', [], null];
    }

    /**
     * @param \Closure(Nomba): mixed $call
     * @param array<string, string>  $query
     * @param array<mixed>|null      $body
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('calls')]
    public function testRequestShape(\Closure $call, string $method, string $path, array $query, ?array $body): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok(['id' => 'x'])]);

        $call($nomba);

        self::assertSame(2, $this->requestCount());
        $request = $this->request(1);
        self::assertSame($method, $request->getMethod());
        self::assertSame('sandbox.nomba.com', $request->getUri()->getHost());
        self::assertSame($path, $request->getUri()->getPath());
        self::assertSame($query, $this->query(1));
        self::assertSame('Bearer ' . self::ACCESS_TOKEN, $request->getHeaderLine('Authorization'));
        self::assertSame(self::ACCOUNT_ID, $request->getHeaderLine('accountId'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        if ($body === []) {
            self::assertSame('{}', (string) $request->getBody());
        } else {
            self::assertSame($body, $this->body(1));
        }
    }

    public function testEveryPublicMethodHasARequestTest(): void
    {
        $covered = [];
        foreach (self::calls() as $name => $_) {
            $covered[explode(' ', $name)[0]] = true;
        }
        $resources = ['accounts', 'virtualAccounts', 'checkout', 'transfers', 'transactions', 'terminals', 'directDebits', 'bills', 'globalPayout', 'globalCollection'];
        $missing = [];
        foreach ($resources as $resource) {
            $class = new \ReflectionProperty(Nomba::class, $resource);
            $type = $class->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            /** @var class-string $className */
            $className = $type->getName();
            foreach ((new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getDeclaringClass()->getName() !== $className || str_starts_with($m->getName(), 'listAll') || str_ends_with($m->getName(), 'All')) {
                    continue;
                }
                if (!isset($covered[$resource . '.' . $m->getName()])) {
                    $missing[] = $resource . '.' . $m->getName();
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testFloatsKeepTheirFraction(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([])]);
        $nomba->transfers->toBank(['amount' => 100.0, 'accountNumber' => '1', 'accountName' => 'n', 'bankCode' => 'b', 'merchantTxRef' => 'r']);

        self::assertStringContainsString('"amount":100.0', (string) $this->request(1)->getBody());
    }

    public function testGlobalPayoutProductionAccountPaths(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok([]), self::ok([])], ['environment' => 'production']);

        $nomba->globalPayout->listAccounts();
        $nomba->globalPayout->getAccount('gp-1');

        self::assertSame('https://api.nomba.com/v1/global-payout/accounts', (string) $this->request(1)->getUri());
        self::assertSame('/v1/global-payout/accounts/gp-1', $this->request(2)->getUri()->getPath());
    }

    public function testLookupsReturnStrings(): void
    {
        $nomba = $this->nomba([self::tokenResponse(), self::ok('JANE DOE'), self::ok(['unexpected'])]);

        self::assertSame('JANE DOE', $nomba->bills->lookupCableTvCustomer(['customerId' => '1', 'cableTvType' => 'DSTV']));
        self::assertSame('', $nomba->bills->lookupBettingCustomer(['providerId' => 'p', 'customerId' => '1']));
    }
}
