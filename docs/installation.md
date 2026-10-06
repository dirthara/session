---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation of Dirthara Session.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required. Composer installs its one
runtime dependency, a PSR interface package it uses:

| Package | Provides |
| --- | --- |
| `psr/clock` `^1.0` | The PSR-20 clock interface, which sessions read the current time from. |

:::note
`psr/clock` holds only the interface. The session manager needs an implementation
of `Psr\Clock\ClockInterface` to read the current time from, which this package
does not ship; see [getting started](getting-started.md#3-a-clock).
:::

## Package installation

Install the package with Composer:

```sh
composer require dirthara/session
```

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/session#readme). Development tooling
includes PHPUnit 13, Mago, and Xdebug.
