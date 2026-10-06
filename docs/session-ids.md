---
id: session-ids
title: Session IDs
sidebar_position: 5
description: What a valid session ID is, how IDs are generated, and how to carry them in a cookie.
---

## Valid IDs

A `SessionId` is 16 to 256 characters, each an ASCII letter, a digit, `-`, or `_`:

```php
use Dirthara\Session\ValueObject\SessionId;

$id = new SessionId('9f86d081884c7d659a2feaa0c55ad015');
$id->value; // '9f86d081884c7d659a2feaa0c55ad015'
```

Any other value throws an `InvalidSessionIdException`. Those characters are safe in a cookie, a URL, a file name, and
a key for any store, so a store never has to escape an ID. The minimum length keeps out IDs too short to be hard to
guess.

A session ID is as good as a password for as long as its session lives: whoever presents it is treated as the visitor. The
exception therefore states only the length of the value it refused, never the value.

## Generating IDs

The manager gets the ID of every new and every regenerated session from a `SessionIdGenerator`.
`RandomSessionIdGenerator` takes 32 bytes, 256 bits, from `random_bytes()`, PHP's cryptographically secure random
source, and writes them as 64 lowercase hexadecimal characters:

```php
use Dirthara\Session\Generator\RandomSessionIdGenerator;

new RandomSessionIdGenerator()->generate()->value;
// 'c3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2'
```

Lowercase hexadecimal keeps two IDs apart in a store that compares keys without regard to letter case, such as a
database column with a case-insensitive collation.

A generator of your own implements `Dirthara\Session\Contract\SessionIdGenerator`, and has to take its IDs from a
cryptographically secure source with at least 64 bits of entropy.

## IDs from a visitor

An ID from a cookie is untrusted input. Turning it into a `SessionId` validates it, and loading it only returns a
session when one is stored under it:

```php
use Dirthara\Session\Exception\InvalidSessionIdException;

try {
    $session = $sessions->load(new SessionId($cookie));
} catch (InvalidSessionIdException) {
    $session = null;
}

$session ??= $sessions->create();
```

Because `create()` always generates the ID, a visitor who sends an ID of their own choosing gets a new session with
another ID.

## The cookie

Carrying the ID is up to the application. For a cookie:

| Attribute | Value | Why |
| --- | --- | --- |
| `Secure` | set | The ID is never sent over an unencrypted connection. |
| `HttpOnly` | set | Scripts in the page, including injected ones, cannot read the ID. |
| `SameSite` | `Lax` or `Strict` | Other sites cannot make the browser send the ID along with their requests. |
| `Path` | `/` | Every page of the application sees the same session. |
| `Expires` or `Max-Age` | the lifetime, from the last save | The browser forgets the ID about when the session expires. |

Send the cookie again after every save, because a save extends the lifetime, and because the ID changes when the
session is regenerated or invalidated.
