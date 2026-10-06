<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Random\Engine;

use function str_repeat;

final readonly class FixedRandomEngine implements Engine
{
    public function generate(): string
    {
        return str_repeat("\xAB", times: 8);
    }
}
