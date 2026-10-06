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
| `save(Session $session): bool` | Stores the session's values under its ID, starts its lifetime again, and returns whether the session is stored. |
| `regenerate(Session $session): void` | Gives the session a new ID. See [regenerating the ID](#regenerating-the-id). |
| `invalidate(Session $session): void` | Removes the session's values and gives it a new ID. See [invalidating](#invalidating). |

Changes to a session stay in the object until it is saved. A session that is not saved is not stored, and a change that
is not saved is lost, so save the session at the end of every request that uses it.

A session without values is never stored. Saving a new, empty session stores nothing, and saving a stored session whose
values were all removed deletes it; both return `false`. Visitors and crawlers that never put anything in their session
therefore never take up space in the store. Once the session holds a value again, the next save stores it.

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

Every save stores the session until its idle lifetime has passed, measured from the moment of saving. A session whose
values did not change since it was loaded or last saved is not written again: the manager only moves its expiry, which
is far cheaper for a store such as a database. Any `put()`, `remove()`, or `clear()` counts as a change, even one that
puts the value that was already there. The lifetime
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

The manager's `regenerate()` gives a session a new ID at once and keeps its values. Regenerate the session whenever the
visitor's privileges change, most importantly right after they log in:

```php
$sessions->regenerate($session);
$session->put('user', $user->id);

$sessions->save($session);
```

An attacker who managed to plant a session ID on a visitor, or saw it before the visitor logged in, then holds an ID
that no longer leads anywhere. This defence against session fixation only works when the application regenerates; the
package cannot know when privileges change.

`$session->id` is the new ID straight away, but the store only learns about it on save: the manager deletes the ID the
session was stored under, and then writes the session under its new ID. Regenerating several times before a save
stores only the last ID. Send the visitor the new ID after saving.

## Invalidating

The manager's `invalidate()` removes every value and gives the session a new ID, as `clear()` followed by
`regenerate()` does. Use it when the visitor logs out:

```php
$sessions->invalidate($session);

$sessions->save($session);
```

Saving deletes the old ID and, because the session is empty, stores nothing under the new one, and returns `false`. The
old ID no longer works anywhere. A value put in the session afterwards, such as a message for the next page, makes the
next save store the session under its new ID.

## When saving fails

## When a save stores nothing

`save()` returns `false`, and stores nothing, when:

- the session has no values, as [described above](#creating-loading-and-saving); or
- a loaded session is no longer in the store. Another request may have regenerated or invalidated it, or the store may
  have removed it. Its changes are lost.

Either way, there is no session behind the ID, and the application should remove the visitor's cookie.

This is what keeps an old ID dead. Without it, a request that loaded the session before another request logged the
visitor out, or regenerated the ID after they logged in, would write the old ID back when it finishes.

## When saving fails

A store reports a failure by throwing; the manager does not catch it. When deleting the old ID of a regenerated session
fails, nothing has changed, and the old ID keeps working until the session is saved again. When writing the new ID
fails after the old ID was deleted, the session is in neither, and saving it again stores it under its new ID. The
manager deletes first on purpose: a failure in between logs the visitor out rather than leaving two working IDs.

## Concurrent requests

Sessions are not locked. When two requests load the same session and both save it, the one that saves last wins, and
the other's changes are lost. A request that saves after another request regenerated or invalidated the session stores
nothing, as [described above](#when-a-save-stores-nothing). Keep values that concurrent requests both change, such as counters, out of the session,
or make sure only one request changes them.

## Sessions belong to their manager

A manager only saves, regenerates, and invalidates the sessions it created or loaded itself. A `Session` built by hand,
or one from another manager, throws a `ForeignSessionException`. An application therefore cannot store a session under
an ID it chose, such as one taken from a cookie: every ID a manager stores came from its generator.

`Session` is a final class rather than a contract. What the manager needs to know about a session, such as the ID it is
stored under, stays between the two.
