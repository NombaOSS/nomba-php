<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * A webhook request failed signature, header or timestamp checks.
 * Respond with HTTP 400 and do not process the event.
 */
final class WebhookVerificationException extends NombaException {}
