<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use RuntimeException;

use function sprintf;

final class SessionSerialisationException extends RuntimeException implements SessionException
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

    public static function unableToSerialise(?Throwable $previous = null): self
    {
        return new self(
            message: 'Unable to serialise the session values: one of them cannot be serialised.',
            previous: $previous,
        );
    }

    public static function unableToDeserialise(?Throwable $previous = null): self
    {
        return new self(
            message: 'Unable to deserialise a session payload: the payload is malformed.',
            previous: $previous,
        );
    }

    public static function unknownClass(): self
    {
        return new self(
            message: 'Unable to deserialise a session payload: it holds an object of a class that does not exist.',
        );
    }

    public static function notSessionValues(string $type): self
    {
        return new self(
            message: sprintf(
                'Unable to deserialise a session payload: it holds a value of type "%s" rather than an array of session values.',
                self::printable($type),
            ),
            context: ['type' => self::printable($type)],
        );
    }
}
