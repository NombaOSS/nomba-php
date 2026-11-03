<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * 401 after the token refresh retry, or the access token could not be obtained.
 */
final class AuthenticationException extends ApiException {}
