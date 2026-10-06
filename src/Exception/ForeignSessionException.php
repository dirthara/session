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

    public static function unknown(string $operation): self
    {
        return new self(
            message: sprintf(
                'Unable to %s the session: this session manager did not create or load it.',
                self::printable($operation),
            ),
            context: ['operation' => self::printable($operation)],
        );
    }
}
