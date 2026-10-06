<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class ForeignSessionException extends InvalidArgumentException implements SessionException
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

    public static function cannotBeSaved(string $class): self
    {
        return new self(
            message: sprintf(
                'Unable to save a session of class "%s": a session manager saves only the sessions that a session manager creates or loads.',
                self::printable($class),
            ),
            context: ['class' => self::printable($class)],
        );
    }
}
