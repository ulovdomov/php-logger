# Symfony Bundle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Symfony bundle that registers the same logging/OpenTelemetry services as the existing Nette `LoggerExtension`, configured declaratively via `ulov_domov_logging`.

**Architecture:** A single `UlovDomovLoggingBundle` extending Symfony's `AbstractBundle` (config schema in `configure()`, service wiring in `loadExtension()`). Three new Symfony-specific OpenTelemetry resource detectors mirror the existing Nette ones. The existing `TracesConsoleLogger` (already an `EventSubscriberInterface`) is reused for console tracing.

**Tech Stack:** PHP 8.1–8.4, Symfony `^6.4 || ^7.0` (DependencyInjection, Config, HttpKernel, HttpFoundation, Security-Core, Console), PHPUnit 10.5, PHPStan level 9.

---

## Background for the implementer

This repo is a PHP logging library (`ulovdomov/logger`, namespace `UlovDomov\Logging`, autoloaded from `src/`). It already ships a Nette DI integration at `src/Logging/DI/LoggerExtension.php`. We are adding the Symfony equivalent. Read these existing files before starting — the new code mirrors them:

- `src/Logging/DI/LoggerExtension.php` — the Nette extension we are mirroring. Note the conditional registration rules (tracer only if `traces.url` set, meter only if `metrics.url` set, OT client/detectors only if either url is set).
- `src/Logging/LoggerContext.php` — constructor `(string|null $environment = null, array $tags = [])`.
- `src/Logging/LoggerContextService.php` — constructor `(LoggerContext $context, Tracer|null $tracer = null, Meter|null $meter = null)`.
- `src/Logging/Monolog/MonologContextProcessor.php` — constructor `(LoggerContextService $loggerContextService)`, implements `Monolog\Processor\ProcessorInterface`.
- `src/Logging/OpenTelemetry/OpenTelemetryClient.php` — constructor `(string|null $url, TransportType $type, string|null $metricsUrl, TransportType $metricsType)`.
- `src/Logging/OpenTelemetry/Traces/Tracer.php` — constructor `(string $name, string|null $instance, OpenTelemetryClient $openTelemetryClient, ResourceDetector $resourceDetector)`.
- `src/Logging/OpenTelemetry/Metrics/Meter.php` — constructor `(string $name, string|null $instance, string $prefix, OpenTelemetryClient $openTelemetryClient, ResourceDetector $resourceDetector, MetricValueStore|null $store = null)`.
- `src/Logging/OpenTelemetry/Resources/ResourceDetector.php` — constructor `(string $environment, string $namespace, string $version, array $resourceDetectors)`.
- `src/Logging/OpenTelemetry/Resources/Detectors/ContextResourceDetector.php` — constructor `(LoggerContext $context)`, implements `OpenTelemetry\SDK\Resource\ResourceDetectorInterface`.
- `src/Logging/OpenTelemetry/Resources/Detectors/NetteHttpResourceDetector.php` and `NetteSecurityResourceDetector.php` — templates for the new Symfony detectors.
- `src/Logging/OpenTelemetry/TransportType.php` — enum, **string values are**: `file`, `grpc`, `http`, `http-protobuf`, `null` (note the hyphen in `http-protobuf`).
- `src/Logging/Console/TracesConsoleLogger.php` — already implements `Symfony\Component\EventDispatcher\EventSubscriberInterface`, constructor `(Tracer $tracer)`.

**Spec:** `docs/superpowers/specs/2026-05-28-symfony-bundle-design.md`.

**Conventions (mandatory):**
- Every PHP file starts with `<?php declare(strict_types = 1);` (spaces around `=`).
- PHPStan level 9, strict rules, checked exceptions — config in `tools/phpstan.neon`.
- Code style: `ulovdomov/php-code-style`. Run `composer run cs-fix` then `composer run cs`.
- All commands run **inside Docker**. Prefix with `make docker` shell or use `composer run <script>` inside the container. The plan shows the `composer run ...` form; run them inside the container.

**Deviation from spec (testing):** The spec described a "mini Kernel" container test. This plan uses a **`ContainerBuilder`-based extension test** as the primary verification (Task 7) because it can assert service definitions AND tags precisely without booting a full framework, plus one lightweight **kernel smoke test** (Task 8) to confirm the bundle boots in a real Symfony app. This honors the intent (verify all services register) with better precision.

---

## File structure

| File | Responsibility |
|---|---|
| `src/Logging/OpenTelemetry/Resources/Detectors/SymfonyKernelResourceDetector.php` | Detector: kernel env, debug, bundle list |
| `src/Logging/OpenTelemetry/Resources/Detectors/SymfonyHttpResourceDetector.php` | Detector: current HTTP request method/url/route |
| `src/Logging/OpenTelemetry/Resources/Detectors/SymfonySecurityResourceDetector.php` | Detector: authenticated user id + roles |
| `src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php` | The bundle: config schema + service wiring |
| `tests/Tests/Package/Symfony/SymfonyKernelResourceDetectorTest.php` | Unit test for kernel detector |
| `tests/Tests/Package/Symfony/SymfonyHttpResourceDetectorTest.php` | Unit test for http detector |
| `tests/Tests/Package/Symfony/SymfonySecurityResourceDetectorTest.php` | Unit test for security detector |
| `tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php` | Extension wiring + tag assertions via ContainerBuilder |
| `tests/Tests/Package/Symfony/Kernel/TestKernel.php` | Minimal kernel for smoke test |
| `tests/Tests/Package/Symfony/BundleBootTest.php` | Kernel smoke test |
| `composer.json` | Add Symfony dev deps + suggest entries |
| `README.md` | Symfony usage section |

---

## Task 1: Add Symfony dev dependencies

**Files:**
- Modify: `composer.json` (`require-dev` and `suggest` blocks)

- [ ] **Step 1: Add Symfony packages to `require-dev`**

In `composer.json`, inside the `"require-dev"` object, add these keys (keep existing entries):

```json
"symfony/framework-bundle": "^6.4 || ^7.0",
"symfony/http-kernel": "^6.4 || ^7.0",
"symfony/http-foundation": "^6.4 || ^7.0",
"symfony/dependency-injection": "^6.4 || ^7.0",
"symfony/config": "^6.4 || ^7.0",
"symfony/console": "^6.4 || ^7.0",
"symfony/event-dispatcher": "^6.4 || ^7.0",
"symfony/security-core": "^6.4 || ^7.0",
"symfony/monolog-bundle": "^3.8"
```

- [ ] **Step 2: Add suggest entries**

In the `"suggest"` object add:

```json
"symfony/framework-bundle": "To integrate the logger into a Symfony application via UlovDomovLoggingBundle"
```

- [ ] **Step 3: Install dependencies inside the container**

Run: `composer update "symfony/*" --no-interaction --prefer-dist`
Expected: Symfony packages resolved and installed, no conflicts. (`contributte/console` already pulls `symfony/console`; versions must reconcile to `^6.4 || ^7.0`.)

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add Symfony dev dependencies for bundle"
```

---

## Task 2: SymfonyKernelResourceDetector

**Files:**
- Create: `src/Logging/OpenTelemetry/Resources/Detectors/SymfonyKernelResourceDetector.php`
- Test: `tests/Tests/Package/Symfony/SymfonyKernelResourceDetectorTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Tests/Package/Symfony/SymfonyKernelResourceDetectorTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyKernelResourceDetector;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class SymfonyKernelResourceDetectorTest extends TestCase
{
    public function testCollectsKernelMetadata(): void
    {
        $bundle = $this->createMock(BundleInterface::class);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $kernel->method('isDebug')->willReturn(false);
        $kernel->method('getBundles')->willReturn(['AcmeBundle' => $bundle]);

        $detector = new SymfonyKernelResourceDetector($kernel);
        $attributes = $detector->getResource()->getAttributes();

        self::assertSame('prod', $attributes->get('symfony.kernel.environment'));
        self::assertFalse($attributes->get('symfony.kernel.debug'));
        self::assertSame(['AcmeBundle'], $attributes->get('symfony.kernel.bundles'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer run tests -- --filter SymfonyKernelResourceDetectorTest`
Expected: FAIL — class `SymfonyKernelResourceDetector` not found.

- [ ] **Step 3: Write the implementation**

Create `src/Logging/OpenTelemetry/Resources/Detectors/SymfonyKernelResourceDetector.php`:

```php
<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\HttpKernel\KernelInterface;

final class SymfonyKernelResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function getResource(): ResourceInfo
    {
        $attributes = [
            'symfony.kernel.environment' => $this->kernel->getEnvironment(),
            'symfony.kernel.debug' => $this->kernel->isDebug(),
            'symfony.kernel.bundles' => \array_keys($this->kernel->getBundles()),
        ];

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer run tests -- --filter SymfonyKernelResourceDetectorTest`
Expected: PASS.

- [ ] **Step 5: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Logging/OpenTelemetry/Resources/Detectors/SymfonyKernelResourceDetector.php tests/Tests/Package/Symfony/SymfonyKernelResourceDetectorTest.php
git commit -m "feat: add SymfonyKernelResourceDetector"
```

---

## Task 3: SymfonyHttpResourceDetector

**Files:**
- Create: `src/Logging/OpenTelemetry/Resources/Detectors/SymfonyHttpResourceDetector.php`
- Test: `tests/Tests/Package/Symfony/SymfonyHttpResourceDetectorTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Tests/Package/Symfony/SymfonyHttpResourceDetectorTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyHttpResourceDetector;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class SymfonyHttpResourceDetectorTest extends TestCase
{
    public function testCollectsRequestMetadata(): void
    {
        $request = Request::create('http://example.com/orders/42?page=2', 'POST');
        $request->attributes->set('_route', 'order_detail');

        $stack = new RequestStack();
        $stack->push($request);

        $detector = new SymfonyHttpResourceDetector($stack);
        $attributes = $detector->getResource()->getAttributes();

        self::assertSame('POST', $attributes->get('http.request.method'));
        self::assertSame('page=2', $attributes->get('http.request.query_string'));
        self::assertSame('http://example.com/orders/42', $attributes->get('http.request.url'));
        self::assertSame('order_detail', $attributes->get('http.route'));
    }

    public function testEmptyResourceWhenNoRequest(): void
    {
        $detector = new SymfonyHttpResourceDetector(new RequestStack());

        self::assertSame([], $detector->getResource()->getAttributes()->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer run tests -- --filter SymfonyHttpResourceDetectorTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `src/Logging/OpenTelemetry/Resources/Detectors/SymfonyHttpResourceDetector.php`:

```php
<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\HttpFoundation\RequestStack;

final class SymfonyHttpResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getResource(): ResourceInfo
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return ResourceInfo::emptyResource();
        }

        $attributes = [];
        $attributes['http.request.method'] = $request->getMethod();

        $queryString = $request->getQueryString();

        if ($queryString !== null && $queryString !== '') {
            $attributes['http.request.query_string'] = $queryString;
        }

        $attributes['http.request.url'] = $request->getSchemeAndHttpHost() . $request->getPathInfo();

        $route = $request->attributes->get('_route');

        if (\is_string($route)) {
            $attributes['http.route'] = $route;
        }

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer run tests -- --filter SymfonyHttpResourceDetectorTest`
Expected: PASS (both test methods).

- [ ] **Step 5: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Logging/OpenTelemetry/Resources/Detectors/SymfonyHttpResourceDetector.php tests/Tests/Package/Symfony/SymfonyHttpResourceDetectorTest.php
git commit -m "feat: add SymfonyHttpResourceDetector"
```

---

## Task 4: SymfonySecurityResourceDetector

**Files:**
- Create: `src/Logging/OpenTelemetry/Resources/Detectors/SymfonySecurityResourceDetector.php`
- Test: `tests/Tests/Package/Symfony/SymfonySecurityResourceDetectorTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Tests/Package/Symfony/SymfonySecurityResourceDetectorTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonySecurityResourceDetector;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class SymfonySecurityResourceDetectorTest extends TestCase
{
    public function testCollectsUserMetadata(): void
    {
        $user = new InMemoryUser('john', null, ['ROLE_USER', 'ROLE_ADMIN']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $detector = new SymfonySecurityResourceDetector($tokenStorage);
        $attributes = $detector->getResource()->getAttributes();

        self::assertSame('john', $attributes->get('symfony.security.id'));
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $attributes->get('symfony.security.roles'));
    }

    public function testEmptyResourceWhenNoToken(): void
    {
        $detector = new SymfonySecurityResourceDetector(new TokenStorage());

        self::assertSame([], $detector->getResource()->getAttributes()->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer run tests -- --filter SymfonySecurityResourceDetectorTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `src/Logging/OpenTelemetry/Resources/Detectors/SymfonySecurityResourceDetector.php`:

```php
<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SymfonySecurityResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function getResource(): ResourceInfo
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return ResourceInfo::emptyResource();
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return ResourceInfo::emptyResource();
        }

        $attributes = [
            'symfony.security.id' => $user->getUserIdentifier(),
            'symfony.security.roles' => $user->getRoles(),
        ];

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer run tests -- --filter SymfonySecurityResourceDetectorTest`
Expected: PASS (both test methods).

- [ ] **Step 5: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Logging/OpenTelemetry/Resources/Detectors/SymfonySecurityResourceDetector.php tests/Tests/Package/Symfony/SymfonySecurityResourceDetectorTest.php
git commit -m "feat: add SymfonySecurityResourceDetector"
```

---

## Task 5: Bundle skeleton + config schema + core (always-on) services

This task creates the bundle with its configuration tree and registers only the always-on services (`LoggerContext`, `LoggerContextService`, `MonologContextProcessor`). OpenTelemetry wiring comes in Task 6. We register the bundle's extension against a raw `ContainerBuilder` in the test.

**Files:**
- Create: `src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php`
- Test: `tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use UlovDomov\Logging\LoggerContext;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\Monolog\MonologContextProcessor;
use UlovDomov\Logging\Symfony\Bundle\UlovDomovLoggingBundle;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class UlovDomovLoggingBundleTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function buildContainer(array $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.logs_dir', \sys_get_temp_dir());

        $bundle = new UlovDomovLoggingBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $builder);

        return $builder;
    }

    public function testCoreServicesAlwaysRegistered(): void
    {
        $builder = $this->buildContainer([
            'environment' => 'prod',
            'tags' => ['app' => 'demo'],
        ]);

        self::assertTrue($builder->hasDefinition(LoggerContext::class));
        self::assertTrue($builder->hasDefinition(LoggerContextService::class));
        self::assertTrue($builder->hasAlias('ulov_domov_logging.context_service'));

        $processor = $builder->getDefinition(MonologContextProcessor::class);
        self::assertArrayHasKey('monolog.processor', $processor->getTags());
    }

    public function testContextReceivesEnvironmentAndTags(): void
    {
        $builder = $this->buildContainer([
            'environment' => 'staging',
            'tags' => ['app' => 'demo'],
        ]);

        $context = $builder->getDefinition(LoggerContext::class);

        self::assertSame('staging', $context->getArgument('$environment'));
        self::assertSame(['app' => 'demo'], $context->getArgument('$tags'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer run tests -- --filter UlovDomovLoggingBundleTest`
Expected: FAIL — class `UlovDomovLoggingBundle` not found.

- [ ] **Step 3: Write the bundle (schema + core services only)**

Create `src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php`:

```php
<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Symfony\Bundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use UlovDomov\Logging\LoggerContext;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\Monolog\MonologContextProcessor;

final class UlovDomovLoggingBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $transportTypes = ['grpc', 'http', 'http-protobuf', 'file', 'null'];

        // @phpstan-ignore-next-line — fluent node builder is loosely typed in Symfony Config
        $definition->rootNode()
            ->children()
                ->scalarNode('environment')->defaultNull()->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('open_telemetry')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')->defaultValue('unknown-ud-app')->end()
                        ->scalarNode('instance')->defaultNull()->end()
                        ->scalarNode('version')->defaultValue('0.0.0')->end()
                        ->scalarNode('namespace')->defaultValue('ud-php-app')->end()
                        ->arrayNode('resource_detectors')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('traces')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url')->defaultNull()->end()
                                ->enumNode('type')->values($transportTypes)->defaultValue('grpc')->end()
                            ->end()
                        ->end()
                        ->arrayNode('metrics')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url')->defaultNull()->end()
                                ->scalarNode('prefix')->defaultValue('udapp')->end()
                                ->enumNode('type')->values($transportTypes)->defaultValue('grpc')->end()
                                ->scalarNode('store')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *     environment: string|null,
     *     tags: array<string, string>,
     *     open_telemetry: array{
     *         name: string,
     *         instance: string|null,
     *         version: string,
     *         namespace: string,
     *         resource_detectors: array<string>,
     *         traces: array{url: string|null, type: string},
     *         metrics: array{url: string|null, prefix: string, type: string, store: string|null},
     *     },
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $contextDef = new Definition(LoggerContext::class, [
            '$environment' => $config['environment'],
            '$tags' => $config['tags'],
        ]);
        $builder->setDefinition(LoggerContext::class, $contextDef);

        $contextServiceDef = new Definition(LoggerContextService::class, [
            '$context' => new Reference(LoggerContext::class),
        ]);
        $builder->setDefinition(LoggerContextService::class, $contextServiceDef);
        $builder->setAlias('ulov_domov_logging.context_service', LoggerContextService::class)
            ->setPublic(true);

        $processorDef = new Definition(MonologContextProcessor::class, [
            '$loggerContextService' => new Reference(LoggerContextService::class),
        ]);
        $processorDef->addTag('monolog.processor');
        $builder->setDefinition(MonologContextProcessor::class, $processorDef);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer run tests -- --filter UlovDomovLoggingBundleTest`
Expected: PASS (both methods).

- [ ] **Step 5: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors. (If PHPStan flags the fluent `rootNode()` chain, the `@phpstan-ignore-next-line` above covers it; do not add broad ignores.)

- [ ] **Step 6: Commit**

```bash
git add src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php
git commit -m "feat: add UlovDomovLoggingBundle with core service wiring"
```

---

## Task 6: OpenTelemetry conditional wiring

Extend `loadExtension()` to register OT services following the Nette extension's conditional rules. The detectors from Tasks 2–4 are registered as private autowired services so users can reference them by FQCN in `resource_detectors`.

**Files:**
- Modify: `src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php`
- Modify: `tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php`

- [ ] **Step 1: Add failing tests for conditional wiring**

Append these methods to `UlovDomovLoggingBundleTest` (inside the class):

```php
    public function testTracerAndMeterAbsentWhenNoUrls(): void
    {
        $builder = $this->buildContainer([
            'open_telemetry' => ['name' => 'svc'],
        ]);

        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Traces\Tracer::class));
        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Metrics\Meter::class));
        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient::class));
    }

    public function testTracesWiringWhenTracesUrlSet(): void
    {
        $builder = $this->buildContainer([
            'open_telemetry' => [
                'name' => 'svc',
                'traces' => ['url' => 'http://collector:4317', 'type' => 'grpc'],
            ],
        ]);

        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient::class));
        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector::class));
        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Resources\Detectors\ContextResourceDetector::class));
        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Traces\Tracer::class));

        $console = $builder->getDefinition(\UlovDomov\Logging\Console\TracesConsoleLogger::class);
        self::assertArrayHasKey('kernel.event_subscriber', $console->getTags());

        // tracer must be wired into the context service
        $contextService = $builder->getDefinition(LoggerContextService::class);
        self::assertInstanceOf(\Symfony\Component\DependencyInjection\Reference::class, $contextService->getArgument('$tracer'));
    }

    public function testMetricsWiringWithJsonStore(): void
    {
        $builder = $this->buildContainer([
            'open_telemetry' => [
                'name' => 'svc',
                'metrics' => ['url' => 'http://collector:4317', 'type' => 'grpc', 'store' => 'json'],
            ],
        ]);

        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Metrics\Meter::class));
        self::assertTrue($builder->hasDefinition('ulov_domov_logging.metrics_store'));
        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Traces\Tracer::class));

        $contextService = $builder->getDefinition(LoggerContextService::class);
        self::assertInstanceOf(\Symfony\Component\DependencyInjection\Reference::class, $contextService->getArgument('$meter'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer run tests -- --filter UlovDomovLoggingBundleTest`
Expected: FAIL — new assertions fail (definitions not registered yet).

- [ ] **Step 3: Implement conditional wiring**

Add these imports to `UlovDomovLoggingBundle.php`:

```php
use UlovDomov\Logging\Console\TracesConsoleLogger;
use UlovDomov\Logging\OpenTelemetry\Metrics\Meter;
use UlovDomov\Logging\OpenTelemetry\Metrics\Store\JsonFileMetricStore;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\ContextResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;
use UlovDomov\Logging\OpenTelemetry\TransportType;
```

In `loadExtension()`, after the `MonologContextProcessor` registration and before the method closes, append:

```php
        $ot = $config['open_telemetry'];
        $tracesUrl = $ot['traces']['url'];
        $metricsUrl = $ot['metrics']['url'];

        $tracerEnabled = $tracesUrl !== null;
        $meterEnabled = $metricsUrl !== null;

        if (!$tracerEnabled && !$meterEnabled) {
            return;
        }

        $contextDetectorDef = new Definition(ContextResourceDetector::class, [
            '$context' => new Reference(LoggerContext::class),
        ]);
        $contextDetectorDef->setAutowired(false);
        $builder->setDefinition(ContextResourceDetector::class, $contextDetectorDef);

        $detectorRefs = [];

        foreach ($ot['resource_detectors'] as $detectorId) {
            $detectorRefs[] = new Reference(\ltrim($detectorId, '@'));
        }

        $detectorRefs[] = new Reference(ContextResourceDetector::class);

        $resourceDetectorDef = new Definition(ResourceDetector::class, [
            '$environment' => (string) $config['environment'],
            '$namespace' => $ot['namespace'],
            '$version' => $ot['version'],
            '$resourceDetectors' => $detectorRefs,
        ]);
        $resourceDetectorDef->setAutowired(false);
        $builder->setDefinition(ResourceDetector::class, $resourceDetectorDef);

        $clientDef = new Definition(OpenTelemetryClient::class, [
            '$url' => $tracesUrl,
            '$type' => TransportType::from($ot['traces']['type']),
            '$metricsUrl' => $metricsUrl,
            '$metricsType' => TransportType::from($ot['metrics']['type']),
        ]);
        $clientDef->setAutowired(false);
        $builder->setDefinition(OpenTelemetryClient::class, $clientDef);

        if ($tracerEnabled) {
            $tracerDef = new Definition(Tracer::class, [
                '$name' => $ot['name'],
                '$instance' => $ot['instance'],
                '$openTelemetryClient' => new Reference(OpenTelemetryClient::class),
                '$resourceDetector' => new Reference(ResourceDetector::class),
            ]);
            $builder->setDefinition(Tracer::class, $tracerDef);
            $contextServiceDef->setArgument('$tracer', new Reference(Tracer::class));

            $consoleDef = new Definition(TracesConsoleLogger::class, [
                '$tracer' => new Reference(Tracer::class),
            ]);
            $consoleDef->addTag('kernel.event_subscriber');
            $builder->setDefinition(TracesConsoleLogger::class, $consoleDef);
        }

        if ($meterEnabled) {
            $store = $ot['metrics']['store'];
            $storeRef = null;

            if ($store === 'json') {
                $storeDef = new Definition(JsonFileMetricStore::class, [
                    '$file' => '%kernel.logs_dir%/ud-metrics.json',
                ]);
                $storeDef->setAutowired(false);
                $builder->setDefinition('ulov_domov_logging.metrics_store', $storeDef);
                $storeRef = new Reference('ulov_domov_logging.metrics_store');
            } elseif ($store !== null) {
                $storeRef = new Reference(\ltrim($store, '@'));
            }

            $meterDef = new Definition(Meter::class, [
                '$name' => $ot['name'],
                '$instance' => $ot['instance'],
                '$prefix' => $ot['metrics']['prefix'],
                '$openTelemetryClient' => new Reference(OpenTelemetryClient::class),
                '$resourceDetector' => new Reference(ResourceDetector::class),
                '$store' => $storeRef,
            ]);
            $builder->setDefinition(Meter::class, $meterDef);
            $contextServiceDef->setArgument('$meter', new Reference(Meter::class));
        }
```

Note: `$contextServiceDef` is the variable already defined earlier in `loadExtension()`; calling `setArgument()` on it mutates the registered definition in place (definitions are objects held by reference in the builder).

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer run tests -- --filter UlovDomovLoggingBundleTest`
Expected: PASS (all five methods).

- [ ] **Step 5: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors. The Metrics namespace is excluded from PHPStan via `tools/phpstan.neon`, but `Meter`/`JsonFileMetricStore` are only *referenced* (class-string usage) here — no new ignores should be needed.

- [ ] **Step 6: Commit**

```bash
git add src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php
git commit -m "feat: add OpenTelemetry conditional wiring to Symfony bundle"
```

---

## Task 7: Register Symfony detectors as referenceable services

The three detectors must be available as container services so users can list them in `resource_detectors`. Register them (private, autowired) so unused ones are removed at compile time (avoids forcing SecurityBundle when security isn't installed).

**Files:**
- Modify: `src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php`
- Modify: `tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php`

- [ ] **Step 1: Add a failing test**

Append to `UlovDomovLoggingBundleTest`:

```php
    public function testSymfonyDetectorsRegisteredWhenOtelEnabled(): void
    {
        $builder = $this->buildContainer([
            'open_telemetry' => [
                'name' => 'svc',
                'traces' => ['url' => 'http://collector:4317', 'type' => 'grpc'],
            ],
        ]);

        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyKernelResourceDetector::class));
        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyHttpResourceDetector::class));
        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonySecurityResourceDetector::class));

        $kernelDetector = $builder->getDefinition(\UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyKernelResourceDetector::class);
        self::assertTrue($kernelDetector->isAutowired());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer run tests -- --filter testSymfonyDetectorsRegisteredWhenOtelEnabled`
Expected: FAIL — detector definitions absent.

- [ ] **Step 3: Implement detector registration**

Add imports to `UlovDomovLoggingBundle.php`:

```php
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyHttpResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyKernelResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonySecurityResourceDetector;
```

Immediately after the `OpenTelemetryClient` definition (still inside the `if (!$tracerEnabled && !$meterEnabled) return;` guarded block), add:

```php
        foreach ([SymfonyKernelResourceDetector::class, SymfonyHttpResourceDetector::class, SymfonySecurityResourceDetector::class] as $detectorClass) {
            $detectorDef = new Definition($detectorClass);
            $detectorDef->setAutowired(true);
            $builder->setDefinition($detectorClass, $detectorDef);
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer run tests -- --filter UlovDomovLoggingBundleTest`
Expected: PASS (all six methods).

- [ ] **Step 5: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Logging/Symfony/Bundle/UlovDomovLoggingBundle.php tests/Tests/Package/Symfony/UlovDomovLoggingBundleTest.php
git commit -m "feat: register Symfony resource detectors as bundle services"
```

---

## Task 8: Kernel smoke test

Boot a real minimal Symfony kernel with the bundle to confirm it compiles and the public context service resolves. This catches boot-time wiring errors the unit tests can't.

**Files:**
- Create: `tests/Tests/Package/Symfony/Kernel/TestKernel.php`
- Create: `tests/Tests/Package/Symfony/BundleBootTest.php`

- [ ] **Step 1: Create the test kernel**

Create `tests/Tests/Package/Symfony/Kernel/TestKernel.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Package\Symfony\Kernel;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use UlovDomov\Logging\Symfony\Bundle\UlovDomovLoggingBundle;

final class TestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $loggingConfig
     */
    public function __construct(private readonly array $loggingConfig)
    {
        parent::__construct('test', false);
    }

    /**
     * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new UlovDomovLoggingBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
            ]);
            $container->loadFromExtension('ulov_domov_logging', $this->loggingConfig);
        });
    }

    public function getCacheDir(): string
    {
        return \sys_get_temp_dir() . '/ud-logging-bundle-test/cache/' . \spl_object_id($this);
    }

    public function getLogDir(): string
    {
        return \sys_get_temp_dir() . '/ud-logging-bundle-test/log';
    }
}
```

- [ ] **Step 2: Write the smoke test**

Create `tests/Tests/Package/Symfony/BundleBootTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Tests\Package\Symfony\Kernel\TestKernel;
use UlovDomov\Logging\LoggerContextService;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class BundleBootTest extends TestCase
{
    public function testKernelBootsAndExposesContextService(): void
    {
        $kernel = new TestKernel([
            'environment' => 'test',
            'tags' => ['app' => 'boot-test'],
        ]);
        $kernel->boot();

        $contextService = $kernel->getContainer()->get('ulov_domov_logging.context_service');

        self::assertInstanceOf(LoggerContextService::class, $contextService);

        $kernel->shutdown();
    }
}
```

- [ ] **Step 3: Run the smoke test**

Run: `composer run tests -- --filter BundleBootTest`
Expected: PASS. If FrameworkBundle requires additional minimal config in the installed Symfony version, add only the missing required keys to the `framework` extension config (do not add unrelated options).

- [ ] **Step 4: Lint + static analysis**

Run: `composer run cs-fix && composer run cs && composer run phpstan`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add tests/Tests/Package/Symfony/Kernel/TestKernel.php tests/Tests/Package/Symfony/BundleBootTest.php
git commit -m "test: add Symfony kernel boot smoke test for the bundle"
```

---

## Task 9: Documentation

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Read the existing README to match its style**

Read `README.md`. Find where the Nette installation/configuration is documented.

- [ ] **Step 2: Add a Symfony section**

Add a section mirroring the Nette docs. Include:
- Register the bundle in `config/bundles.php`:
  ```php
  UlovDomov\Logging\Symfony\Bundle\UlovDomovLoggingBundle::class => ['all' => true],
  ```
- Example `config/packages/ulov_domov_logging.yaml`:
  ```yaml
  ulov_domov_logging:
      environment: '%kernel.environment%'
      tags:
          app: 'my-app'
      open_telemetry:
          name: 'my-app'
          version: '1.0.0'
          namespace: 'my-namespace'
          resource_detectors:
              - 'UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyHttpResourceDetector'
              - 'UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonySecurityResourceDetector'
          traces:
              url: 'http://collector:4317'
              type: 'grpc'
  ```
- A note: `LoggerContextService` is autowirable; inject it where needed. The `MonologContextProcessor` is auto-tagged for MonologBundle. Sentry's `LoggerContextIntegration` is provided but not auto-registered — wire it manually if using `sentry/sentry-symfony`.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document Symfony bundle usage"
```

---

## Task 10: Full quality gate

- [ ] **Step 1: Run the complete suite**

Run: `composer run cs && composer run phpstan && composer run tests`
Expected: all three pass (code sniffer clean, PHPStan level 9 clean, all tests green including the existing Nette tests).

- [ ] **Step 2: Verify no regression in Nette tests**

Run: `composer run tests -- --filter DITest && composer run tests -- --filter LoggerContextServiceTest`
Expected: PASS — the Nette integration is untouched.

- [ ] **Step 3: Final review commit (if any cs-fix changes remain)**

```bash
git add -A
git commit -m "chore: finalize Symfony bundle quality gate" || echo "nothing to commit"
```

---

## Self-review notes (verification of this plan against the spec)

- **Scope coverage:** LoggerContext/Service (Task 5) ✅, Monolog processor tag (Task 5) ✅, OT client/resourceDetector/contextDetector/tracer/meter conditional rules (Task 6) ✅, console traces via existing `TracesConsoleLogger` tag (Task 6) ✅, three Symfony detectors (Tasks 2–4, registered Task 7) ✅, AbstractBundle config schema with `ulov_domov_logging` key (Task 5) ✅, Symfony 6.4/7 dev deps (Task 1) ✅, tests (Tasks 5–8) ✅, README + suggest (Tasks 1, 9) ✅.
- **Excluded by spec (confirmed absent from plan):** Tracy adapter, Slim middleware, `MonologLoggerFactory`, `app_log_dir` config key, automatic Sentry registration, automatic detector activation.
- **Type consistency:** `TransportType` values use the hyphenated `http-protobuf` (matches enum). Constructor named-args (`$environment`, `$tags`, `$context`, `$tracer`, `$meter`, `$name`, `$instance`, `$openTelemetryClient`, `$resourceDetector`, `$prefix`, `$store`, `$file`) match the real constructors listed in "Background".
- **Known deviation:** `JsonFileMetricStore` requires a `$file` argument (the Nette extension omits it, which would fail if ever exercised). The plan supplies `%kernel.logs_dir%/ud-metrics.json`. Flag for reviewer.
- **Testing deviation:** ContainerBuilder extension test (precise, fast) is primary; one kernel smoke test added. Both honor the "verify all services register" intent.
