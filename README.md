# Php Logger

Php Logger

## Installation

Add following to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ulovdomov/php-logger"
    }
  ]
}
```

And run:

```shell
composer require ulovdomov/logger
```

## Nette DI extension

This is only example, must be replaced!

1. First need register and configure `UlovDomov\Logging\DI\LoggerExtension`

```neon
extensions:
    logger: UlovDomov\Logging\DI\LoggerExtension
```

And configure it:

```neon
logger:
    environment: %env.ENVIRONMENT%         # default is: null
    appLogDir: %logDir%/app                # default is: Debugger::$logDirectory . '/app'
    tags:
        app: 'data-source'                 # specific tags for app
```

## Usage

### DI service - `LoggerContextService`

The DI extension registers the LoggerContextService in the DI container:

- This service is used to configure logging information for loggers such as `Tracy`, `Sentry`, and `Monolog`
- The information is logged to Tracy `.log` files, Sentry, and API logs via Monolog (if installed)
- Tag conventions:
  - Tags should be lowercase and underscored
  - If the tag value is an enum, it is recommended to use lowercase and hyphens
  - The `app` tag identifies the application if there are multiple apps under the same project
  - Additional tags should ideally have an `app.` prefix to differentiate them from native tags of Sentry and the Contributte package
  - Examples: `app=data-source`, `app.source=ulov-domov`, or numerical tags such as `app.size=33`

The most relevant methods in the LoggerContextService are:

```php
public function addTag(string $name, string|int|float $value): void

public function addContext(string|int $key, mixed $value): void

public function setSpan(string|int $identifier, string|null $status = null): void
```

- The service also includes a `traceId` (or process/request ID) which is internally generated to allow tracking errors within a single process
- To manage subprocesses within the same process, use the `setSpan` method

#### Interface `FingerprintedException`

- Used for grouping exceptions that need to be monitored in the application
- Implements the method `getFingerprint(): string|null`, which returns, for example, a hash
- The value from `getFingerprint()` is utilized:
  - By `Sentry` to group issues accordingly
  - Logged into `Tracy` `.log` files, allowing logs to be grouped via commands.
  - The `Monolog` logger does not log this information.

```php
interface FingerprintedException extends \Throwable
{
    public function getFingerprint(): string|null;
}
```

### Sentry

For Sentry, you need to install the `contributte/sentry` package and register it in the DI container

```neon
extensions:
    sentry: Contributte\Sentry\DI\SentryExtension
```

- There is an integration available, `UlovDomov\Logging\Sentry\LoggerContextIntegration`, which must be used
- Below is the recommended configuration for the `SentryExtension`

```neon
sentry:
    enable: true
    integrations: false
    client:
        attach_stacktrace: true
        dsn: %env.SENTRY_DNS%
        integrations: [
            Sentry\Integration\EnvironmentIntegration()
            Sentry\Integration\ErrorListenerIntegration()
            Sentry\Integration\ExceptionListenerIntegration()
            Sentry\Integration\FatalErrorListenerIntegration()
            Sentry\Integration\FrameContextifierIntegration(null)
            Sentry\Integration\ModulesIntegration()
            Sentry\Integration\RequestIntegration(null)
            Sentry\Integration\TransactionIntegration()
            UlovDomov\Logging\Sentry\LoggerContextIntegration()    # This is IMPORTANT
        ]
```

The full configuration for Sentry is available in the documentation: https://contributte.org/packages/contributte/sentry.html

For local development, it is advisable to disable Sentry entirely:

```neon
sentry:
    enable: false
```

### API logger for ELK via Monolog

- To log into ELK, you need to install the `monolog/monolog` package
- The `LoggerExtension`, upon detecting Monolog classes, will register `UlovDomov\Logging\Monolog\MonologLoggerFactory` in the DI container
- The data is logged to the `appLogDir` directory, as specified in the configuration of `LoggerExtension`, see the DI configuration above
- In the application, `MonologLoggerFactory` is used as follows:

```php
/** @var \UlovDomov\Logging\Monolog\MonologLoggerFactory $monologLoggerFactory */
$logger = $monologLoggerFactory->createForMethod(__METHOD__);

$logger->info('Request log', [/* request context and data */]);

$logger->info('Response log', [/* response context and data */]);
```

## Development

### First setup

1. Run for initialization
```shell
make init
```
2. Run composer install
```shell
make composer
```

Use tasks in Makefile:

- To log into container
```shell
make docker
```
- To run code sniffer fix
```shell
make cs-fix
```
- To run PhpStan
```shell
make phpstan
```
- To run tests
```shell
make phpunit
```