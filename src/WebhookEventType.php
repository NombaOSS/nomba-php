<?php

declare(strict_types=1);

namespace Nomba;

/**
 * Values of a webhook event's `event_type`.
 */
final class WebhookEventType
{
    public const PAYMENT_SUCCESS = 'payment_success';
    public const PAYMENT_FAILED = 'payment_failed';
    public const PAYMENT_REVERSAL = 'payment_reversal';
    public const PAYOUT_SUCCESS = 'payout_success';
    public const PAYOUT_FAILED = 'payout_failed';
    public const PAYOUT_REFUND = 'payout_refund';

    public const ALL = [
        self::PAYMENT_SUCCESS,
        self::PAYMENT_FAILED,
        self::PAYMENT_REVERSAL,
        self::PAYOUT_SUCCESS,
        self::PAYOUT_FAILED,
        self::PAYOUT_REFUND,
    ];

    private function __construct() {}
}
