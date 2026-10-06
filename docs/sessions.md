---
id: sessions
title: Sessions
sidebar_position: 4
description: Create, load, and save sessions, work with their values, expire them, and regenerate or invalidate their ID.
---

## Creating, loading, and saving

A `SessionManager` works with the sessions in one store:

| Method | Does |
| --- | --- |
| `create(): Session` | Returns a new, empty session with a new ID. Nothing is stored until it is saved. |
| `load(SessionId $id): ?Session` | Returns the session stored under the ID, or `null` when there is none or it has expired. |
| `save(Session $session): void` | Stores the session's values under its ID, and starts its lifetime again. |

Changes to a session stay in the object until it is saved. A session that is not saved is not stored, and a change that
is not saved is lost, so save the session at the end of every request that uses it.

`load()` returns a new object each time. Two objects loaded for the same ID do not see each other's changes.

## Values

A session holds values under string keys:

| Method | Does |
| --- | --- |
| `has(string $key): bool` | Whether the session has the key, even when its value is `null`. |
| `get(string $key, mixed $default = null): mixed` | Returns the value under the key, or the default when the session does not have the key. |
| `put(string $key, mixed $value): void` | Stores a value under the key, replacing any value already there. |
| `remove(string $key): void` | Removes the key, and does nothing when the session does not have it. |
| `clear(): void` | Removes every key, and keeps the ID. |

```php
$session->put('user', 42);

$session->has('user');          // true
$session->get('user');          // 42
$session->get('locale', 'en');  // 'en'

$session->remove('user');
```

The default is used only for a missing key, so a stored `null`, `0`, or `''` is returned as it is.

Saving turns the values into a payload with the manager's [serialiser](serialisation.md), and loading turns them back
into copies. A value the serialiser cannot handle, such as a closure, makes `save()` throw a
`SessionSerialisationException` before anything is stored.

## Expiry

Every save stores the session until its idle lifetime has passed, measured from the moment of saving. The lifetime
comes from the configuration:

```php
$sessions = $factory->create(new SessionConfiguration('memory', new Lifetime(Duration::minutes(30))));
```

A session that is saved on every request therefore lives until it has not been used for the whole idle lifetime. `load()`
treats a session as expired from the moment its lifetime ends, deletes it from the store, and returns `null`.

A `Lifetime` is longer than zero and at most 400 days, which is as long as browsers keep a cookie. Any other duration
throws an `InvalidSessionLifetimeException`; `Lifetime::MAXIMUM_MILLISECONDS` holds the maximum.

:::caution
The manager only removes an expired session when something tries to load it. A session that is never loaded again
stays in the store until the store removes it. Whether and when a store removes expired sessions is up to the store;
the memory store never does.
:::

## Regenerating the ID

`regenerate()` gives a session a new ID and keeps its values. Regenerate the session whenever the visitor's privileges
change, most importantly right after they log in:

```php
$session->regenerate();
$session->put('user', $user->id);

$sessions->save($session);
```

An attacker who managed to plant a session ID on a visitor, or saw it before the visitor logged in, then holds an ID
that no longer leads anywhere. This defence against session fixation only works when the application regenerates; the
package cannot know when privileges change.

The new ID takes effect on save: the manager writes the session under its new ID, and then deletes every ID the
session replaced since it was last saved. Send the visitor the new ID after saving.

## Invalidating

`invalidate()` removes every value and gives the session a new ID, as `clear()` followed by `regenerate()` does. Use it
when the visitor logs out:

```php
$session->invalidate();

$sessions->save($session);
```

Saving stores the empty session under its new ID and deletes the old one, so the old ID no longer works anywhere.

## When saving fails

A store reports a failure by throwing; the manager does not catch it. When writing the session fails, nothing is
deleted and the session keeps the IDs it replaced, so saving it again finishes the job. When deleting a replaced ID
fails, the session is already stored under its new ID, and it keeps the replaced IDs that are left, so saving it again
deletes them. Until then, an old ID keeps working.

## Concurrent requests

Sessions are not locked. When two requests load the same session and both save it, the one that saves last wins, and
the other's changes are lost. Keep values that concurrent requests both change, such as counters, out of the session,
or make sure only one request changes them.

## Saving foreign sessions

The manager saves the `Session` objects that a manager creates or loads. Saving another implementation of the
`Session` contract throws a `ForeignSessionException`.
