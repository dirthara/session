---
id: exceptions
title: Exceptions
sidebar_position: 7
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
| `InvalidSessionConfigurationException` | `InvalidArgumentException` | The lifetime is zero or longer than 400 days, or an option is missing without a default, or has another type than the one read. |
| `SessionDriverNotFoundException` | `RuntimeException` | A manager is created with, or a driver asked for, a name without a driver. |

## Using sessions

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `InvalidSessionIdException` | `InvalidArgumentException` | A value is not a valid session ID. Its context holds only the length of the value. See [session IDs](session-ids.md). |
| `ForeignSessionException` | `InvalidArgumentException` | A manager is asked to save a `Session` that no manager created or loaded. See [sessions](sessions.md#saving-foreign-sessions). |

A visitor can send a cookie that is not a session ID at any time. Read it with `SessionId::tryFrom()`, which returns
`null` instead of throwing an `InvalidSessionIdException`, and start a new session.

The manager does not catch what a store throws. Exceptions from a driver package's store implement `SessionException`
as well; see [stores and drivers](drivers.md#the-store-contract) and [when saving fails](sessions.md#when-saving-fails).

Names that appear in messages and context, such as a driver name or the class of a foreign session, have their control
characters escaped, so a name cannot forge a line in a log.
