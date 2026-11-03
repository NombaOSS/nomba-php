<?php

declare(strict_types=1);

// Create a hosted checkout order and redirect the customer to its link.

require __DIR__ . '/../vendor/autoload.php';

use Nomba\Nomba;

$nomba = new Nomba();

$order = $nomba->checkout->createOrder([
    'order' => [
        'orderReference' => 'order-1042',
        'callbackUrl' => 'https://example.com/checkout/callback',
        'customerEmail' => 'customer@example.com',
        'amount' => 15000.00,
        'currency' => 'NGN',
    ],
    'tokenizeCard' => false,
]);

echo "Send the customer to: {$order['checkoutLink']}\n";

// Later (for example on the callback, or after the payment_success webhook):
$details = $nomba->checkout->getTransaction(['idType' => 'ORDER_REFERENCE', 'id' => $order['orderReference']]);
echo 'Payment status: ' . ($details['transactionDetails']['status'] ?? 'unknown') . "\n";
