# Security Policy

## Supported versions

| Branch | Releases | Status |
| --- | --- | --- |
| `0.1` | 0.1.x | Active |

While the package is pre-1.0, only the latest release line receives fixes.

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/session/security/advisories/new)
form. Do not disclose vulnerabilities in public issues or pull requests.

Include the affected version or commit, PHP version, a minimal reproduction,
and the impact and conditions needed to trigger the issue. Maintainers will
acknowledge and assess the report. Confirmed fixes are published with an
advisory crediting the reporter unless they prefer otherwise.

## Scope

Report security issues in the session manager, sessions, session ID validation
and generation, serialisation, the memory driver, the store contract checks,
exception handling, or development configuration. That includes:

- a generated ID that can be predicted;
- a session ID the application or a visitor can choose, rather than the
  generator;
- an ID that works again after it was regenerated or invalidated, including
  through concurrent requests;
- a session that outlives its idle or absolute lifetime;
- a session ID or session value that leaks into an exception.

The package does not read or write cookies, authenticate visitors, or decide
when a session ID has to be regenerated. Cookie attributes, regenerating the ID
when a visitor's privileges change, invalidating the session on logout, choosing
an absolute lifetime, and pruning are the application's responsibility; see
[session IDs](docs/session-ids.md) and [sessions](docs/sessions.md). Sessions
are not locked, so concurrent requests on one session overwrite each other's
changes.

`RandomSessionIdGenerator` reads from PHP's cryptographically secure
`Random\Engine\Secure` engine by default. Its constructor accepts another
`Random\Engine` so that tests can make IDs predictable or the random source
fail. Passing an engine that is not cryptographically secure, such as
`Random\Engine\Mt19937`, outside tests makes session IDs predictable. That is
a misconfiguration in the application, not a vulnerability in this package;
see [generating IDs](docs/session-ids.md#generating-ids). A custom
`SessionIdGenerator` is likewise the application's responsibility.

The native serialiser restores PHP objects from payloads, including running
their restoration hooks, so it trusts the store; see
[serialisation](docs/serialisation.md). Protecting the store from untrusted
writers, and checking and changing in one step in `replace()`, `touch()`, and
`delete()`, belong to the driver and its backend.

Bugs in PHP or third-party dependencies should also be reported upstream.
Application code and the sensitivity of data an application chooses to store
are the application's responsibility.
