<?php

declare(strict_types=1);

namespace Dirthara\Session\Serialiser;

use Throwable;
use SplObjectStorage;
use ReflectionReference;
use __PHP_Incomplete_Class;
use Dirthara\Session\Contract\SessionSerialiser;
use Dirthara\Session\Exception\SessionSerialisationException;

use function is_array;
use function is_object;
use function serialize;
use function unserialize;
use function get_debug_type;
use function array_key_exists;
use function set_error_handler;
use function restore_error_handler;

final readonly class NativeSessionSerialiser implements SessionSerialiser
{
    /**
     * @throws SessionSerialisationException
     */
    public function serialise(array $values): string
    {
        try {
            return serialize($values);
        } catch (Throwable $exception) {
            throw SessionSerialisationException::unableToSerialise($exception);
        }
    }

    /**
     * @throws SessionSerialisationException
     */
    public function deserialise(string $payload): array
    {
        if ($payload === '') {
            throw SessionSerialisationException::unableToDeserialise();
        }

        $malformed = false;

        set_error_handler(static function () use (&$malformed): bool {
            $malformed = true;

            return true;
        });

        try {
            // @mago-expect analysis:mixed-assignment A payload can hold any value until it is checked below
            $restored = unserialize($payload, ['allowed_classes' => true]);
        } catch (Throwable $exception) {
            throw SessionSerialisationException::unableToDeserialise($exception);
        } finally {
            restore_error_handler();
        }

        if ($malformed) {
            throw SessionSerialisationException::unableToDeserialise();
        }

        if (!is_array($restored)) {
            throw SessionSerialisationException::notSessionValues(get_debug_type($restored));
        }

        $references = [];
        /** @var SplObjectStorage<object, null> $objects */
        $objects = new SplObjectStorage();

        if ($this->hasIncompleteClass($restored, $objects, $references)) {
            throw SessionSerialisationException::unknownClass();
        }

        return $restored;
    }

    /**
     * @param SplObjectStorage<object, null> $objects
     * @param array<string, true> $references
     */
    private function hasIncompleteClass(mixed $value, SplObjectStorage $objects, array &$references): bool
    {
        if ($value instanceof __PHP_Incomplete_Class) {
            return true;
        }

        if (is_object($value)) {
            if ($objects->offsetExists($value)) {
                return false;
            }

            $objects->offsetSet($value, null);
            // @mago-expect analysis:invalid-type-cast Mangled property keys allow private values to be inspected without invoking hooks
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return false;
        }

        // @mago-expect analysis:mixed-assignment Session values and object properties can hold any value
        foreach ($value as $key => $nested) {
            $reference = ReflectionReference::fromArrayElement($value, $key);
            if ($reference !== null) {
                $identity = $reference->getId();
                if (array_key_exists($identity, $references)) {
                    continue;
                }
                $references[$identity] = true;
            }

            if ($this->hasIncompleteClass($nested, $objects, $references)) {
                return true;
            }
        }

        return false;
    }
}
