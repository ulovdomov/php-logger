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
    openTelemetry:
        traces:
            name: 'name-of-my-app'            # name for tracer
            url: 'https://example.com:4317'   # endpoint URL address with port (this enable traces)
            type: 'grpc'                      # can use more transport types (grpc, http, http-protobuf)
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
```

- The service also includes a `traceId` (or process/request ID) which is internally generated to allow tracking errors within a single process

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

## Log traces via OpenTelemetry

Install the following required packages:

```shell
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

And configure it:

```neon
logger:
    # ... other values
    openTelemetry:
        traces:
            name: 'name-of-my-app'            # name for tracer
            url: 'https://example.com:4317'   # endpoint URL address with port
            type: 'grpc'                      # can use more transport types (grpc, file, http, http-protobuf, null)
```

### GRPC (recommended)

`type: 'grpc'`

1. For `open-telemetry/transport-grpc`, you need to have installed `grpc` and `protobuf` PHP extensions.
2. Then you can install the package:

```shell
composer require open-telemetry/transport-grpc
```

### HTTP Protobuf

`type: 'http-protobuf'`

- Better than simple HTTP, because data are transported as binary instead of JSON.
- Requires installed `protobuf` PHP extension.

### HTTP

`type: 'http'`

- Basic option without binary encoding.
- No additional PHP extension required.

### File (dev only)

`type: 'file'`

```neon
logger:
    # ... other values
    openTelemetry:
        traces:
            name: 'name-of-my-app'
            url: %LOG_DIR%   # log directory (<url>/open-telemetry/transport.log)
            type: 'file'
```

- This is only for development purposes
- It will log to `<url>/open-telemetry/transport.log`

### Null (dev or tests only)

`type: 'null'`

```neon
logger:
    # ... other values
    openTelemetry:
        traces:
            name: 'name-of-my-app'
            url: 'https://example.com:4317'
            type: 'null'       # you can disable logging
```

- This is only for development or test purposes
- It will discard all logs

## Usage

```php
/** @var \UlovDomov\Logging\LoggerContextService $contextService */
$tracer = $contextService->getTracer();
// or you can get \UlovDomov\Logging\OpenTelemetry\Traces\Tracer from DI container as a service

$attributes = [/* you can set an array of attributes for root span */];
$tracer->enable($attributes);

for ($i = 0; $i < 3; $i++) {
    // start a span, register some events
    $span = $tracer->startSpan('loop-' . $i);

    $span->setAttribute('remote_ip', '1.2.3.4')
         ->setAttribute('country', 'CZ');

    $span->addEvent('found_login' . $i, [
        'id' => $i,
        'username' => 'user' . $i,
    ]);

    $span->addEvent('generated_session', [
        'id' => md5((string) microtime(true)),
    ]);

    $span->end();
}

$tracer->end(); // must be called at the end of the script
```

## Additional methods

```php
/** @var \UlovDomov\Logging\OpenTelemetry\Traces\Tracer $tracer */

// check if tracer is enabled
$tracer->isEnabled();

// get root span (for example, to add more info to this span)
$tracer->getRoot();

// get current span
$current = $tracer->getCurrent();
```

There is also static access to the current span:

‼️ This is only for exceptional cases. Not recommended for regular use. ‼️

```php
$current = \UlovDomov\Logging\OpenTelemetry\Traces\Span::getCurrent();

$current->getContext()->getSpanId();
$current->getContext()->getTraceId();
```


### StdOut logging via Monolog

- To log via stdout, you need to install the `monolog/monolog` package
- The `LoggerExtension`, upon detecting Monolog classes, will register `UlovDomov\Logging\Monolog\MonologLoggerFactory` in the DI container
- In the application, `MonologLoggerFactory` is used as follows:

```php
/** @var \UlovDomov\Logging\Monolog\MonologLoggerFactory $monologLoggerFactory */
$logger = $monologLoggerFactory->createStdOut('channel-name');

// or alternatively $monologLoggerFactory->create('channel-name', stdOut: true);

$logger->info('Request log', [/* request context and data */]);

$logger->info('Response log', [/* response context and data */]);
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