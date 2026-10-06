<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use RuntimeException;

use function sprintf;

final class SessionDriverNotFoundException extends RuntimeException implements SessionException
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

    public static function for(string $driver): self
    {
        return new self(
            message: sprintf(
                'Unable to find the session driver "%s": no driver is registered under that name.',
                self::printable($driver),
            ),
            context: [
                'driver' => self::printable($driver),
            ],
        );
    }
}
