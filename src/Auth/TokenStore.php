<?php

declare(strict_types=1);

namespace Nomba\Auth;

/**
 * Where the SDK keeps its access token. The default is in-memory (per process).
 *
 * Implement this to share one token between workers or servers (for example in
 * Redis or a database), since Nomba recommends a single authenticating instance.
 * Treat the stored value as a secret.
 */
interface TokenStore
{
    public function get(): ?Token;

    public function set(?Token $token): void;
}
