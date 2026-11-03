<?php

declare(strict_types=1);

namespace Nomba;

/**
 * Values of `data.status` on outflow responses (transfers, airtime, data, bills).
 * The SDK never throws on these; check them yourself. See the README table and
 * https://developer.nomba.com/docs/api-basics/response-codes.
 */
final class TransactionStatus
{
    /** Delivered to the destination. Do not refund. */
    public const SUCCESS = 'SUCCESS';
    /** Funds returned to your Nomba wallet. Refund your customer. */
    public const REFUND = 'REFUND';
    /** Will not proceed. Contact Nomba support. */
    public const CANCELLED = 'CANCELLED';
    /** Debit failed; Nomba may retry. Wait for the webhook. */
    public const PAYMENT_FAILED = 'PAYMENT_FAILED';
    /** Nomba may retry through another vendor. Wait for the webhook. */
    public const REVERSED_BY_VENDOR = 'REVERSED_BY_VENDOR';
    /** Still processing. Wait for the webhook or requery. */
    public const PENDING_BILLING = 'PENDING_BILLING';
    /** Still processing (v1 endpoints). Wait for the webhook or requery. */
    public const PROCESSING = 'PROCESSING';

    /** Statuses that are not final: wait for a webhook or requery. */
    public const PENDING = [self::PAYMENT_FAILED, self::REVERSED_BY_VENDOR, self::PENDING_BILLING, self::PROCESSING];

    /** @codeCoverageIgnore */
    private function __construct() {}
}
