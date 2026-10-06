---
id: drivers
title: Stores and drivers
sidebar_position: 6
description: The session store contract, the memory store, drivers, configuration, and creating a manager.
---

## The store contract

A `SessionStore` keeps sessions under their IDs. The manager does everything else: it generates IDs, decides when a
session has expired, and moves a session to its new ID. A store sees only valid `SessionId` objects and
`StoredSession` objects, each holding the session's `values`, keyed by string, and the `expiresAt` moment it expires.

| Method | Does |
| --- | --- |
| `read(SessionId $id): ?StoredSession` | Returns the session under the ID, or `null` when there is none. |
| `write(SessionId $id, StoredSession $session): void` | Stores a session under the ID, replacing any session already there. |
| `delete(SessionId $id): void` | Removes the session under the ID, and succeeds when there is none. |

A store reports a failure by throwing. A driver package lets its exceptions implement
`Dirthara\Session\Exception\SessionException` as well as its own package's interface, so that a caller can catch
every session failure with one type.

A store does not have to remove expired sessions: the manager never returns one, whatever the store hands back. A store
for a backend that can expire keys itself can pass `expiresAt` on, and any other store can remove sessions whose
`expiresAt` has passed whenever it likes, so that sessions nobody loads again do not take up space.

## The memory store

`MemorySessionStore` keeps its sessions in a PHP array. It suits tests, and sessions within a single long-running
process:

- Its sessions are lost when the process ends, and other processes cannot see them.
- It never removes expired sessions. They stay in memory until something loads or deletes them, so a long-running
  process that creates many sessions keeps growing.
- It keeps the values themselves rather than copies. An object stored in a session is the same object in every
  session loaded from it, so changing it changes the stored session without a save.
- It never fails.

## Drivers

A `SessionDriver` creates a store from a `SessionConfiguration`. A `SessionDriverRegistry` holds drivers by name:

```php
use Dirthara\Session\Driver\Memory\MemorySessionDriver;
use Dirthara\Session\Driver\SessionDriverRegistry;

$drivers = new SessionDriverRegistry();
$drivers->register('memory', new MemorySessionDriver());

$drivers->has('memory');       // true
$drivers->driver('memory');    // the MemorySessionDriver
```

Driver names are matched exactly, including their letter case. Registering a second driver under a name throws a
`DuplicateSessionDriverException` and keeps the first; asking for a name without a driver throws a
`SessionDriverNotFoundException`.

The registry implements two contracts, so code can depend on only what it uses: `SessionDriverProvider` for `has()` and
`driver()`, and `SessionDriverRegistry`, which adds `register()`.

`MemorySessionDriver` ignores the configuration's options and creates a new, separate `MemorySessionStore` each time.

## Creating a manager

`SessionManagerFactory` creates a [manager](sessions.md) on a store from the driver a configuration names:

```php
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Generator\RandomSessionIdGenerator;
use Dirthara\Session\SessionManagerFactory;
use Dirthara\Session\ValueObject\Duration;

$factory = new SessionManagerFactory($drivers, new RandomSessionIdGenerator(), $clock);

$sessions = $factory->create(new SessionConfiguration('memory', Duration::hours(2)));
```

Each manager gets its own store from the driver, so whether two managers share their sessions depends on the driver:
two memory managers never do, while managers of a driver for shared storage usually do. A configuration that names a
driver that is not registered throws a `SessionDriverNotFoundException`. Code that only creates managers can depend on
the `SessionManagerFactory` contract in `Dirthara\Session\Contract`, and code that works with sessions on the
`SessionManager` contract.

A `SessionManager` can also be constructed directly, from a store, an ID generator, a clock, and the configuration whose
lifetime it uses.

## Configuration

A `SessionConfiguration` names a driver, sets the session lifetime, and carries the options for the driver:

```php
$configuration = new SessionConfiguration('database', Duration::hours(2), [
    'table' => 'sessions',
    'prune' => true,
]);
```

| Argument | Type | Meaning |
| --- | --- | --- |
| `driver` | `string` | The name the driver is registered under. |
| `lifetime` | `Duration` | How long a session lives after it was last saved: longer than zero, and at most 400 days. |
| `options` | `array<string, mixed>` | The driver's options. Defaults to none. |

A `Duration` is created in milliseconds, seconds, minutes, or hours, as `Duration::minutes(30)`, and holds a whole,
non-negative number of milliseconds. A negative amount, or one that does not fit in an integer of milliseconds, throws
an `InvalidDurationException`. A lifetime of zero or longer than 400 days, the most browsers keep a cookie, throws an
`InvalidSessionConfigurationException`; `SessionConfiguration::MAXIMUM_LIFETIME_MILLISECONDS` holds the maximum.

A driver reads its options with typed accessors:

| Method | Returns |
| --- | --- |
| `string(string $key, ?string $default = null)` | The option, which has to be a string. |
| `int(string $key, ?int $default = null)` | The option, which has to be an integer. |
| `bool(string $key, ?bool $default = null)` | The option, which has to be a boolean. |
| `has(string $key)` | Whether the option is present, even when its value is `null`. |

The default is used only when the option is missing. A missing option without a default, or a present option of
another type, throws an `InvalidSessionConfigurationException`. Values are never converted: the string `'3306'` is not
an integer, and a present `null` is not a missing option. The exception names the option and the type it has, but never
contains its value, because an option can hold a credential.

## Writing a driver

A driver package implements `SessionStore` for its backend and a `SessionDriver` that creates it, reading the options it
needs from the configuration:

```php
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Contract\SessionDriver;
use Dirthara\Session\Contract\SessionStore;

final readonly class DatabaseSessionDriver implements SessionDriver
{
    public function create(SessionConfiguration $configuration): SessionStore
    {
        return new DatabaseSessionStore(
            table: $configuration->string('table', 'sessions'),
            prune: $configuration->bool('prune', true),
        );
    }
}
```

The store decides how to write the values. Whatever it uses to turn them into something it can write, it reads back
from the backend, so a store that restores PHP objects from its backend has to be sure that nothing else can write
there.
