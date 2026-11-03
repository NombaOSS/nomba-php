<?php

declare(strict_types=1);

// Send a bank transfer safely: look up the account, use your own merchantTxRef,
// and on a timeout or pending status, requery instead of sending again.

require __DIR__ . '/../vendor/autoload.php';

use Nomba\Exception\ConnectionException;
use Nomba\Exception\NotFoundException;
use Nomba\Nomba;
use Nomba\TransactionStatus;

$nomba = new Nomba();

$accountNumber = '0123456789';
$bankCode = '058';

$recipient = $nomba->transfers->lookupAccount(['accountNumber' => $accountNumber, 'bankCode' => $bankCode]);

// Store this reference with your order before sending, so a crash or timeout
// can be resolved by requerying it rather than by sending again.
$merchantTxRef = 'order-1042-payout';

try {
    $transfer = $nomba->transfers->toBank([
        'amount' => 2500.00,
        'accountNumber' => $accountNumber,
        'accountName' => $recipient['accountName'] ?? '',
        'bankCode' => $bankCode,
        'merchantTxRef' => $merchantTxRef,
        'narration' => 'Order 1042 payout',
    ]);
    $status = $transfer['status'] ?? null;
} catch (ConnectionException $e) {
    // Includes timeouts. The transfer may or may not have been processed.
    $status = null;
}

if ($status === null || in_array($status, TransactionStatus::PENDING, true)) {
    try {
        $transaction = $nomba->transactions->get(['merchantTxRef' => $merchantTxRef]);
        $status = $transaction['status'];
    } catch (NotFoundException $e) {
        // Nomba never received it: it is now safe to send again with the same merchantTxRef.
        $status = 'NOT_RECEIVED';
    }
}

echo match ($status) {
    TransactionStatus::SUCCESS => "Delivered.\n",
    TransactionStatus::REFUND => "Refunded to your wallet. Refund your customer.\n",
    'NOT_RECEIVED' => "Not received by Nomba. Retry with the same merchantTxRef.\n",
    default => "Status {$status}: wait for the payout webhook or requery later.\n",
};
