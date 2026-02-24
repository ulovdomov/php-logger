# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`ulovdomov/logger` is a PHP logging library with OpenTelemetry integration, Tracy logger, Sentry, and Monolog support. It targets PHP 8.1–8.4 and uses the Nette Framework DI container.

## Development Environment

The project runs in Docker. A Composer auth token stored in `.composer-token.txt` is required for private repositories.

```bash
make init          # First-time setup: prompts for token, starts Docker, installs deps
make docker        # Shell into the PHP container
make rebuild       # Rebuild Docker containers
make composer      # Run composer install inside container
```

## Build & Quality Commands

All commands run inside Docker via `make` targets or directly with `composer run` inside the container:

```bash
# Code style (PHP CodeSniffer with ulovdomov/php-code-style ruleset)
make cs            # or: composer run cs
make cs-fix        # or: composer run cs-fix

# Static analysis (PHPStan level 9 with strict rules + checked exceptions)
make phpstan       # or: composer run phpstan

# Tests (PHPUnit 10.5)
make phpunit       # or: composer run tests
```

### Running a single test

Inside the Docker container:
```bash
composer run tests -- --filter TestClassName
# or: composer run tests -- --filter testMethodName
```

## Architecture

**Namespace root:** `UlovDomov\Logging` (autoloaded from `src/Logging/`)

### Core Components

- **`LoggerContextService`** — Main facade. Provides the unified logging API (tags, user context, breadcrumbs, etc.).
- **`LoggerContext`** — Trait that stores context data (tags, user info, IP, etc.) used by the service.
- **`TracyLogger`** — Extends Tracy's logger with context enrichment.
- **`FingerprintedException`** — Interface for custom exception grouping in error tracking.

### DI Integration

- **`DI/LoggerExtension`** — Nette DI extension that registers all services based on NEON configuration. This is the main entry point for consumers of the library.

### OpenTelemetry (`OpenTelemetry/`)

- **`OpenTelemetryClient`** — Initializes the OT SDK, creates tracer/meter providers.
- **`TransportType`** — Enum (`Grpc`, `Http`, `HttpProtobuf`, `File`, `Null`) defining transport backends.
- **`Traces/Tracer`** — Span creation and management.
- **`Metrics/Meter`** — Counter, gauge, histogram, observable metrics (experimental, excluded from PHPStan).
- **`Resources/Detectors/`** — Nette-specific resource detectors (Application, Context, HTTP, Security, Session) that attach metadata to telemetry.
- **`Transport/`** — File and Null transport implementations for local dev/testing.

### Integrations

- **`Sentry/SentryLogger`** — Bridges logging to Sentry via contributte/sentry.
- **`Monolog/`** — Monolog handler/formatter for StdOut and ELK logging.
- **`Console/TracesConsoleLogger`** — Wraps console command execution in OT spans.
- **`Slim/TracesSlimLogger`** — HTTP request/response logging middleware for Slim.

## CI Pipeline

GitHub Actions runs on every push/PR across PHP 8.1, 8.2, 8.3, 8.4. The pipeline runs, in order: code sniffer, PHPStan, PHPUnit.

## Key Conventions

- All PHP files use `declare(strict_types = 1)` (note the spaces around `=`).
- PHPStan is at level 9 with strict rules and checked exceptions enabled (`@throws` annotations are required).
- The PHPCS ruleset excludes `ForbiddenAnnotations` (to allow `@throws` for PHPStan) and `ClassConstantTypeHint` (for multi-PHP-version support).
- Tests live in `tests/Tests/Package/` with namespace `Tests\Package\`. Test DI config is in `tests/config/common.neon`.
