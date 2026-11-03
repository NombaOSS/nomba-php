<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * The request timed out. For POST/PUT/DELETE calls the request may still have
 * been processed: requery with your merchantTxRef before trying again.
 */
final class TimeoutException extends ConnectionException {}
