---
id: intro
title: Dirthara Session
sidebar_position: 1
description: Server-side sessions for PHP and the Dirthara framework.
---

Dirthara Session keeps server-side sessions: values that belong to one visitor and outlive a single request. A session
is known by a random ID, which the application hands to the visitor, usually in a cookie, and gets back on the next
request. The values stay on the server.

The package is storage-neutral. A session manager keeps sessions in a `SessionStore`, which a named driver creates
from configuration. The package ships a store in PHP memory, for tests and for sessions within a single PHP process.
Stores for a database and other backends are separate packages. Reading and writing the cookie is up to the
application, so the package works with any HTTP layer.

| Piece | Does | Read |
| --- | --- | --- |
| `Session` | Holds a session's values | [Sessions](sessions.md) |
| `SessionManager` | Creates, loads, saves, regenerates, invalidates, and expires sessions | [Sessions](sessions.md) |
| `SessionId` | A validated session ID | [Session IDs](session-ids.md) |
| `RandomSessionIdGenerator` | Generates session IDs from 256 random bits | [Session IDs](session-ids.md) |
| `NativeSessionSerialiser` | Turns session values into a payload and back | [Serialisation](serialisation.md) |
| `SessionManagerFactory` | Creates a manager from a named driver and its configuration | [Stores and drivers](drivers.md) |
| `SessionDriverRegistry` | Holds the drivers by name | [Stores and drivers](drivers.md) |
| `MemorySessionDriver` | Creates a store that keeps its sessions in PHP memory | [Stores and drivers](drivers.md) |

```php
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Driver\Memory\MemorySessionDriver;
use Dirthara\Session\Driver\SessionDriverRegistry;
use Dirthara\Session\Generator\RandomSessionIdGenerator;
use Dirthara\Session\Serialiser\NativeSessionSerialiser;
use Dirthara\Session\SessionManagerFactory;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;

$drivers = new SessionDriverRegistry();
$drivers->register('memory', new MemorySessionDriver());

$factory = new SessionManagerFactory(
    $drivers,
    new RandomSessionIdGenerator(),
    new NativeSessionSerialiser(),
    $clock,
);
$sessions = $factory->create(new SessionConfiguration('memory', new Lifetime(Duration::hours(2))));

$session = $sessions->create();
$session->put('user', 42);
$sessions->save($session);
```

[Getting started](getting-started.md) explains each step, including the `$clock` and the cookie. See
[installation](installation.md) for the requirements, and [exceptions](exceptions.md) for every failure the package
reports.
