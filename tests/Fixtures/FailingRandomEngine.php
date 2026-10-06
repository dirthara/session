<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Random\Engine;
use Random\RandomException;

final readonly class FailingRandomEngine implements Engine
{
    public function generate(): string
    {
        throw new RandomException('No source of randomness is available.');
    }
}
