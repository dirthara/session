<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class DuplicateSessionDriverException extends InvalidArgumentException implements SessionException
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

    public static function alreadyRegistered(string $driver): self
    {
        return new self(
            message: sprintf(
                'Unable to register the session driver "%s": a driver is already registered under that name, and a driver is never replaced.',
                self::printable($driver),
            ),
            context: ['driver' => self::printable($driver)],
        );
    }
}
