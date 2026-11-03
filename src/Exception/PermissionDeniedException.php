<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * HTTP 403: the credentials are valid but not allowed to do this.
 */
final class PermissionDeniedException extends ApiException {}
