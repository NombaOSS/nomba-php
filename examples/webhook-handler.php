<?php

declare(strict_types=1);

// Plain PHP webhook endpoint. Serve with: php -S localhost:8000 examples/webhook-handler.php
// Works the same in any framework: pass the raw body and the request headers.

require __DIR__ . '/../vendor/autoload.php';

use Nomba\Exception\WebhookVerificationException;
use Nomba\Webhook;
use Nomba\WebhookEventType;

$signingKey = getenv('NOMBA_WEBHOOK_SIGNING_KEY');
if (!is_string($signingKey) || $signingKey === '') {
    http_response_code(500);
    exit;
}

// Read the body exactly as sent. Never json_decode and re-encode it before verifying.
$rawBody = (string) file_get_contents('php://input');

/** @var array<string, string> $server */
$server = $_SERVER;

try {
    // $_SERVER carries headers as HTTP_NOMBA_SIGNATURE etc.; that form is accepted.
    // In Laravel/Symfony pass $request->headers->all(); with PSR-7, $request->getHeaders().
    // No tolerance: Nomba doesn't document whether retries get a fresh timestamp,
    // so one could reject legitimate retries. Deduplicate on requestId instead.
    $event = Webhook::verify($rawBody, $server, $signingKey);
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}

// Nomba may deliver the same event more than once: deduplicate on requestId.
switch ($event['event_type']) {
    case WebhookEventType::PAYMENT_SUCCESS:
        // Mark the order paid.
        break;
    case WebhookEventType::PAYOUT_SUCCESS:
    case WebhookEventType::PAYOUT_FAILED:
    case WebhookEventType::PAYOUT_REFUND:
        // Update the payout record.
        break;
}

http_response_code(200);
echo 'ok';
