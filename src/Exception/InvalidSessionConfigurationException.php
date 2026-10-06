<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;
use function get_debug_type;

final class InvalidSessionConfigurationException extends InvalidArgumentException implements SessionException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function invalidLifetime(string $driver, int $milliseconds, int $maximum): self
    {
        return new self(
            message: sprintf(
                'Unable to configure the "%s" session: the lifetime has to be between 1 and %d milliseconds, %d given.',
                self::printable($driver),
                $maximum,
                $milliseconds,
            ),
            context: ['driver' => self::printable($driver), 'lifetime' => $milliseconds, 'maximum' => $maximum],
        );
    }

    public static function missingOption(string $driver, string $key): self
    {
        return new self(
            message: sprintf(
                'Unable to configure the "%s" session: the option "%s" is required and has no default.',
                self::printable($driver),
                self::printable($key),
            ),
            context: ['driver' => self::printable($driver), 'option' => self::printable($key)],
        );
    }

    public static function invalidOptionType(string $driver, string $key, string $expected, mixed $value): self
    {
        return new self(
            message: sprintf(
                'Unable to configure the "%s" session: the option "%s" has to be of type %s, %s given.',
                self::printable($driver),
                self::printable($key),
                $expected,
                get_debug_type($value),
            ),
            context: [
                'driver' => self::printable($driver),
                'option' => self::printable($key),
                'expected' => $expected,
                'actual' => get_debug_type($value),
            ],
        );
    }
}
