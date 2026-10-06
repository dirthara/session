---
id: exceptions
title: Exceptions
sidebar_position: 8
description: Every exception Dirthara Session throws, and when.
---

Every exception the package throws implements `Dirthara\Session\Exception\SessionException`, so one `catch` handles
any of them. Each exception also extends the SPL exception that fits it and carries a `context` array with the values
that describe the failure. Context and messages never contain a session ID, a session value, or a configuration value.

```php
use Dirthara\Session\Exception\SessionException;

try {
    $drivers->register($name, $driver);
} catch (SessionException $exception) {
    $logger->error($exception->getMessage(), $exception->context);
}
```

## Configuration

These are thrown while an application is being set up, and point to a mistake in its code or configuration.

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `DuplicateSessionDriverException` | `InvalidArgumentException` | A second driver is registered under a name. |
| `InvalidDurationException` | `InvalidArgumentException` | A duration is negative, or does not fit in an integer of milliseconds. |
| `InvalidSessionConfigurationException` | `InvalidArgumentException` | An option is missing without a default, or has another type than the one read. |
| `InvalidSessionLifetimeException` | `InvalidArgumentException` | A lifetime is zero. |
| `SessionDriverNotFoundException` | `RuntimeException` | `SessionFactory::create()` is given, or a driver provider asked for, a name without a driver. |

## Using sessions

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `InvalidSessionIdException` | `InvalidArgumentException` | A value is not a valid session ID. Its context holds only the length of the value. See [session IDs](session-ids.md). |
| `SessionSerialisationException` | `RuntimeException` | `save()` is given values that cannot be serialised. A payload that cannot be deserialised throws it from the serialiser, but `load()` treats it as a missing session. See [serialisation](serialisation.md). |
| `SessionIdGenerationException` | `RuntimeException` | `create()`, `regenerate()`, or `invalidate()` needs a new ID and the random source fails to provide the bytes for it. See [session IDs](session-ids.md#generating-ids). |
| `SessionStoreContractException` | `RuntimeException` | A store breaks one of the `SessionStoreContract` checks, while a driver package tests it. See [testing a driver](drivers.md#testing-a-driver). |
| `ForeignSessionException` | `InvalidArgumentException` | A manager is asked to save, regenerate, or invalidate a `Session` it did not create or load. See [sessions](sessions.md#sessions-belong-to-their-manager). |

A visitor can send a cookie that is not a session ID at any time. Read it with `SessionId::tryFrom()`, which returns
`null` instead of throwing an `InvalidSessionIdException`, and start a new session.

The manager does not catch what a store throws. Exceptions from a driver package's store implement `SessionException`
as well; see [stores and drivers](drivers.md#the-store-contract) and [when saving fails](sessions.md#when-saving-fails).

Names that appear in messages and context, such as a driver name, have their control characters escaped, so a name cannot forge a line in a log.
