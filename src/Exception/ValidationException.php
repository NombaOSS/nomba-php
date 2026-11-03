<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * HTTP 400 or 422, or a client-side check failed before the request was sent (bad path parameter, missing argument).
 */
final class ValidationException extends ApiException {}
