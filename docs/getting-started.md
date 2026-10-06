---
id: getting-started
title: Getting started
sidebar_position: 3
description: Create a session manager from a driver, and load, use, and save a session in a request.
---

This page wires every piece together: drivers, an ID generator, a clock, a factory, a manager, and the cookie that
carries the session ID. Each piece has its own page with the details.

## 1. Drivers

A driver creates the store a manager keeps its sessions in. Register each driver the application uses under a name:

```php
use Dirthara\Session\Driver\Memory\MemorySessionDriver;
use Dirthara\Session\Driver\SessionDriverRegistry;

$drivers = new SessionDriverRegistry();
$drivers->register('memory', new MemorySessionDriver());
```

The memory driver keeps sessions in the PHP process, so they are gone when the process ends. That suits tests, and
long-running processes that serve every request themselves. A web application that runs PHP per request needs a driver
for shared storage, such as a database, which comes from its own package. See [stores and drivers](drivers.md).

## 2. An ID generator

The manager asks a generator for the ID of every new session, and every regenerated one:

```php
use Dirthara\Session\Generator\RandomSessionIdGenerator;

$ids = new RandomSessionIdGenerator();
```

`RandomSessionIdGenerator` takes 256 bits from PHP's cryptographically secure random source. See
[session IDs](session-ids.md).

## 3. A clock

The manager reads the current time from a PSR-20 clock to decide when sessions expire. Any implementation of
`Psr\Clock\ClockInterface` works, such as one the application already has, or this one:

```php
use Psr\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
```

Tests can pass a clock that returns a fixed time and move it forward, so they do not have to wait for a session to
expire.

## 4. A manager

```php
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\SessionManagerFactory;
use Dirthara\Session\ValueObject\Duration;

$factory = new SessionManagerFactory($drivers, $ids, new SystemClock());

$sessions = $factory->create(new SessionConfiguration('memory', Duration::hours(2)));
```

The configuration names the driver, sets how long a session lives after it was last saved, and carries the driver's
options. Each call to `create()` asks the driver for a new store.

## 5. A request

The package does not read or write cookies. At the start of a request, load the session the visitor's cookie names,
or create one; at the end, save it and send its ID back:

```php
use Dirthara\Session\ValueObject\SessionId;

$cookie = $_COOKIE['session'] ?? null;
$id = is_string($cookie) ? SessionId::tryFrom($cookie) : null;

$session = $id === null ? null : $sessions->load($id);
$session ??= $sessions->create();

$session->put('last_seen', time());

$sessions->save($session);

setcookie('session', $session->id->value, [
    'expires' => time() + 7200,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
```

`load()` returns `null` for an ID without a live session, and `SessionId::tryFrom()` returns `null` for a cookie that
is not a valid session ID; both mean the visitor starts a new session. A visitor can therefore never choose their
own session ID: every new session gets one from the generator.

Send the cookie after every save, because saving extends the session's lifetime, and because the ID changes when the
session is [regenerated](sessions.md#regenerating-the-id). See [sessions](sessions.md) for what a session holds and
when to regenerate it.
