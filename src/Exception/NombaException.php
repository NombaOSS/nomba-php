<?php

declare(strict_types=1);

namespace Nomba\Exception;

/**
 * Base class for every exception the SDK throws.
 */
class NombaException extends \RuntimeException
{
    private ?Reference $reference = null;

    /**
     * For money-moving calls (transfers, bill purchases, chargeSavedCard): the
     * reference the request was sent with, whether you passed it or the SDK
     * generated it. Null for every other call.
     */
    public function getReference(): ?Reference
    {
        return $this->reference;
    }

    /**
     * Attach the reference of the failed call. Set once; later calls are ignored.
     *
     * @internal
     */
    public function withReference(Reference $reference): static
    {
        $this->reference ??= $reference;

        return $this;
    }
}
