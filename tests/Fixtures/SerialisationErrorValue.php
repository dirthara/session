<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Error;

final readonly class SerialisationErrorValue
{
    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new Error('Unable to serialise object state.');
    }
}
