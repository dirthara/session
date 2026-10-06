---
id: serialisation
title: Serialisation
sidebar_position: 7
description: How Dirthara Session turns session values into payloads, and what the native serialiser trusts.
---

A store keeps string payloads. A `SessionSerialiser` turns a session's values into a payload when the session is
saved, and back into the values when it is loaded. Every store therefore keeps sessions the same way, and a driver never
has to decide how to write PHP values.

## The native serialiser

`NativeSessionSerialiser` uses PHP's `serialize()` and `unserialize()`, so a session can hold any value PHP can
serialise: scalars, `null`, arrays, enums, and objects with the objects they hold. Values are serialised when the
session is saved, so what is stored is each object as it is at that moment, and loading gives back copies: two sessions
loaded for the same ID never share an object.

The manager also compares serialised payloads to decide whether a session changed. A serialiser that turns equal
values into the same payload every time, as `NativeSessionSerialiser` does, lets an unchanged session be touched
rather than written again; one that does not still works, but writes the whole session on every save.

| Failure | Thrown as | What the manager does |
| --- | --- | --- |
| A value that cannot be serialised, such as a closure or an anonymous class | `SessionSerialisationException` | `save()` throws it before it touches the store. |
| A payload that is empty, malformed, or followed by extra data | `SessionSerialisationException` | `load()` returns `null`. |
| A payload holding an object of a class that no longer exists | `SessionSerialisationException` | `load()` returns `null`. |
| A payload that holds something other than an array of values | `SessionSerialisationException` | `load()` returns `null`. |

A session that was saved before a class was renamed or removed therefore starts over after a deployment, rather than
coming back with an unusable `__PHP_Incomplete_Class`. Arrays and object properties are checked recursively, including
private properties, with cycles and repeated references handled safely. The exception never contains the payload or a
value.

PHP turns a numeric string such as `'42'` into an integer when it is an array key, so a value put under `'42'` is kept
under `42`. `get('42')` still finds it.

:::danger
Only use `NativeSessionSerialiser` with a store that nothing untrusted can write to.

Deserialising lets a payload create an object of any class the application has loaded. That class's
`__unserialize()`, `__wakeup()`, and `__destruct()` methods run while the payload is restored, so anyone who can write
a forged payload to the store can run code in those methods. This is PHP object injection.

The serialiser restores every class on purpose, so that objects in a session, such as a `DateTimeImmutable` or a value
object, come back as they were saved. The store is therefore part of the application's trust boundary: protect write
access to a session database or server as you would protect the application's code. The visitor never sees the
payload, only the session ID.
:::

Failures thrown by native `serialize()` or `unserialize()`, including an `Error` from corrupt native object state, are
wrapped in `SessionSerialisationException` with the original failure as `previous`. This handling is confined to native
serialisation; unrelated programmer errors still escape.

## Another format

Implement `SessionSerialiser` to use another format, such as JSON for sessions that only hold arrays and scalars, which
cannot carry objects at all. A serialiser throws a `SessionSerialisationException` for values or a payload it cannot
handle, so that the manager treats an unreadable payload as a missing session.
