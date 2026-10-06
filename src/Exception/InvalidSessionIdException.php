<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidSessionIdException extends InvalidArgumentException implements SessionException
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

    public static function malformed(int $length): self
    {
        return new self(
            message: sprintf(
                'Unable to use the session ID of %d bytes: a session ID has to be 32 to 256 letters, digits, hyphens, or underscores.',
                $length,
            ),
            context: ['length' => $length],
        );
    }
}
