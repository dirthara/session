<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use RuntimeException;
use Dirthara\Session\Exception\SessionException;
use Dirthara\Session\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements SessionException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
