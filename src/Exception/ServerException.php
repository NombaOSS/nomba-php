<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * HTTP 5xx from Nomba. For GET requests the SDK has already retried.
 */
final class ServerException extends ApiException {}
