<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * The idempotency reference a failed money-moving call was sent with.
 *
 * `field` is the request field that carried it ("merchantTxRef", or
 * "order.orderReference" for checkout->chargeSavedCard()). Use `value` to
 * requery before retrying, and retry with the same value.
 */
final class Reference
{
    public function __construct(
        public readonly string $field,
        public readonly string $value,
    ) {}
}
