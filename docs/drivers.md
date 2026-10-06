---
id: drivers
title: Stores and drivers
sidebar_position: 6
description: The session store contract, the memory store, drivers, configuration, and creating a manager.
---

## The store contract

A `SessionStore` keeps sessions under their IDs. The manager does everything else: it generates IDs, serialises the
values, decides when a session has expired, and moves a session to its new ID. A store sees only valid `SessionId`
objects and `StoredSession` objects, each holding the session's string `payload`, the `createdAt` moment it was first
stored, and the `expiresAt` moment it expires.

The manager hands a store every moment in UTC, including the `$now` it prunes with, whatever time zone the clock
returns. A store keeps them as UTC, or as a Unix timestamp, so that it compares them correctly in its own queries; a
store that writes them as local times without a zone, such as into a `DATETIME` column, can prune sessions hours early
or late once servers or the database run in another time zone.

| Method | Does |
| --- | --- |
| `read(SessionId $id): ?StoredSession` | Returns the session under the ID, or `null` when there is none. |
| `write(SessionId $id, StoredSession $session): void` | Stores a session under the ID, replacing any session already there. |
| `replace(SessionId $id, StoredSession $session): bool` | Stores a session under the ID only when there already is one, and returns whether there was. |
| `touch(SessionId $id, DateTimeImmutable $expiresAt): bool` | Changes only the expiry of the session under the ID when there is one, keeps its payload and `createdAt`, and returns whether there was one. |
| `delete(SessionId $id): bool` | Removes the session under the ID, and returns whether there was one. |
| `prune(DateTimeImmutable $now): int` | Removes every session whose `expiresAt` is at or before `$now`, and returns how many it removed. |

`replace()`, `touch()`, and `delete()` have to check and change in one step, because the manager relies on their answer when
requests run at the same time. A database store, for example, runs one `UPDATE` or `DELETE` and returns whether it
affected a row; a store that reads first and writes afterwards can bring back a session another request just deleted.
The manager only calls `write()` for an ID it has just generated.

A store reports a failure by throwing. A driver package lets its exceptions implement
`Dirthara\Session\Exception\SessionException` as well as its own package's interface, so that a caller can catch
every session failure with one type.

A store never has to check expiry when it reads: the manager never returns an expired session, whatever the store hands
back. Expired sessions are removed by `prune()`, which the application calls on a schedule; see
[pruning](sessions.md#pruning). A store for a backend that expires keys itself, such as Redis, can pass `expiresAt` on
when it writes and touches, and return `0` from `prune()`.

## The memory store

`MemorySessionStore` keeps its sessions in a PHP array. It suits tests, and sessions within a single long-running
process:

- Its sessions are lost when the process ends, and other processes cannot see them.
- Expired sessions stay in memory until `prune()` removes them, so a long-running process has to prune regularly or
  keep growing.
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
use Dirthara\Session\Serialiser\NativeSessionSerialiser;
use Dirthara\Session\SessionManagerFactory;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;

$factory = new SessionManagerFactory(
    $drivers,
    new RandomSessionIdGenerator(),
    new NativeSessionSerialiser(),
    $clock,
);

$sessions = $factory->create(new SessionConfiguration('memory', new Lifetime(Duration::hours(2))));
```

Each manager gets its own store from the driver, so whether two managers share their sessions depends on the driver:
two memory managers never do, while managers of a driver for shared storage usually do. A configuration that names a
driver that is not registered throws a `SessionDriverNotFoundException`. Code that only creates managers can depend on
the `SessionManagerFactory` contract in `Dirthara\Session\Contract`, and code that works with sessions on the
`SessionManager` contract.

A `SessionManager` can also be constructed directly, from a store, an ID generator, a serialiser, a clock, and a
`Lifetime`.

## Configuration

A `SessionConfiguration` names a driver, sets the session lifetime, and carries the options for the driver:

```php
$configuration = new SessionConfiguration('database', new Lifetime(Duration::hours(2)), [
    'connection' => 'default',
    'table' => 'sessions',
]);
```

| Argument | Type | Meaning |
| --- | --- | --- |
| `driver` | `string` | The name the driver is registered under. |
| `lifetime` | `Lifetime` | How long a session lives. See [expiry](sessions.md#expiry). |
| `options` | `array<string, mixed>` | The driver's options. Defaults to none. |

A `Lifetime` takes its idle lifetime, and optionally its absolute lifetime, as a `Duration`, which is created in
milliseconds, seconds, minutes, or hours, as `Duration::minutes(30)`, and holds a whole, non-negative number of
milliseconds. A negative amount, or one that does not fit in an integer of milliseconds, throws an
`InvalidDurationException`. A lifetime of zero or longer than 400 days, the most browsers keep a cookie, throws an
`InvalidSessionLifetimeException`.

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
            connection: $configuration->string('connection', 'default'),
            table: $configuration->string('table', 'sessions'),
        );
    }
}
```

The store writes the payload as it is, and hands back exactly what it wrote. It never has to look inside: the
[serialiser](serialisation.md) owns the format, and the backend owns who may write to it.
