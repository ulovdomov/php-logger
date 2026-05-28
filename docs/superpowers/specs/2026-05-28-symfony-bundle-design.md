# Symfony Bundle pro `ulovdomov/logger` — návrh

**Datum:** 2026-05-28
**Stav:** schváleno k implementaci

## Cíl

Knihovna `ulovdomov/logger` dnes nabízí integraci do Nette přes
`UlovDomov\Logging\DI\LoggerExtension`. Symfony uživatelé takovou integraci
nemají a musí si všechny služby registrovat ručně. Tento návrh přidává
**Symfony bundle**, který zaregistruje stejnou sadu služeb deklarativně
přes konfiguraci — paralela k Nette extension.

## Rozsah (scope)

Bundle pokrývá **Symfony-native subset** funkcí Nette extension:

| Funkce | V bundle? | Poznámka |
|---|---|---|
| `LoggerContext` + `LoggerContextService` | ✅ | jádro, vždy |
| OpenTelemetry `Tracer` | ✅ | jen když `traces.url` != null |
| OpenTelemetry `Meter` | ✅ | jen když `metrics.url` != null |
| `OpenTelemetryClient`, `ResourceDetector`, `ContextResourceDetector` | ✅ | když je nakonfigurován traces nebo metrics url |
| Monolog `MonologContextProcessor` | ✅ | tag `monolog.processor` |
| Monolog `MonologLoggerFactory` | ❌ | MonologBundle si handlery řeší sám |
| Console traces (`TracesConsoleLogger`) | ✅ | tag `kernel.event_subscriber`, jen když je tracer aktivní |
| Symfony resource detektory (Kernel/Http/Security) | ✅ | nové třídy, opt-in přes config |
| Tracy adapter | ❌ | v Symfony nedává smysl |
| Slim middleware | ❌ | mimo Symfony stack |
| Sentry `LoggerContextIntegration` | ➖ | stejně jako Nette: jen poskytnutá třída, neregistruje se automaticky |

## Cílové verze

- **Symfony:** `^6.4 || ^7.0` (6.4 LTS do 2027, 7.x aktivní)
- **PHP:** beze změny vůči core balíku (`>=8.1 <8.5`)
- Konfigurační styl: **`AbstractBundle`** (Symfony 6.1+) — vše v jedné třídě.

## Struktura souborů

```
src/Logging/
├── Symfony/Bundle/
│   ├── UlovDomovLoggingBundle.php          # AbstractBundle: configure() + loadExtension()
│   └── Resources/config/
│       └── services.php                    # (volitelně) statické definice společné všem configům
└── OpenTelemetry/Resources/Detectors/
    ├── SymfonyKernelResourceDetector.php
    ├── SymfonyHttpResourceDetector.php
    └── SymfonySecurityResourceDetector.php
```

- Namespace bundle: `UlovDomov\Logging\Symfony\Bundle`
- Namespace detektorů: `UlovDomov\Logging\OpenTelemetry\Resources\Detectors`
  (vedle stávajících `Nette*ResourceDetector`)
- Console tracing **nepotřebuje novou třídu** — stávající
  `UlovDomov\Logging\Console\TracesConsoleLogger` už implementuje
  `EventSubscriberInterface` s `getSubscribedEvents()`. Bundle ho jen
  zaregistruje s tagem `kernel.event_subscriber`.

## Composer závislosti

Přidat do `require-dev` (pro testy a PHPStan):

```
symfony/framework-bundle: ^6.4 || ^7.0
symfony/http-kernel:       ^6.4 || ^7.0
symfony/console:           ^6.4 || ^7.0
symfony/security-core:     ^6.4 || ^7.0
symfony/event-dispatcher:  ^6.4 || ^7.0
symfony/monolog-bundle:    ^3.8        (pro test boot + Monolog processor tag)
```

**Hard `require` se nemění** — Nette uživatelé Symfony nepotřebují, stejně jako
dnes nepotřebují contributte balíky (jsou v `require-dev` + `suggest`).

Do `suggest` přidat řádek směřující Symfony uživatele k `symfony/framework-bundle`.

## Konfigurační schéma

Klíč: `ulov_domov_logging`. Třída bundle: `UlovDomovLoggingBundle`.

```yaml
ulov_domov_logging:
    environment: '%kernel.environment%'      # nullable string
    tags:                                    # array<string,string>
        deployment: 'prod-1'

    open_telemetry:
        name: 'unknown-ud-app'
        instance: null
        version: '0.0.0'
        namespace: 'ud-php-app'
        resource_detectors: []               # pole service referencí ('@id') / FQCN, autowire na ResourceDetectorInterface

        traces:
            url: null                        # null => Tracer se NEregistruje
            type: 'grpc'                     # grpc|http|http_protobuf|file|null

        metrics:
            url: null                        # null => Meter se NEregistruje
            prefix: 'udapp'
            type: 'grpc'
            store: null                      # null | 'json' | service ID (MetricValueStore)
```

Pravidla (shodná s `LoggerExtension`):

- `traces.url === null` ⇒ `Tracer` se NEregistruje; `LoggerContextService::$tracer` = null.
- `metrics.url === null` ⇒ `Meter` se NEregistruje; `LoggerContextService::$meter` = null.
- `traces.url !== null || metrics.url !== null` ⇒ registrují se `OpenTelemetryClient`,
  `ResourceDetector`, `ContextResourceDetector`.
- `type` hodnoty odpovídají `TransportType` enum (`TransportType::from()`).
  Snake_case `http_protobuf` ↔ enum value `http_protobuf`.

Schéma se definuje v `UlovDomovLoggingBundle::configure(DefinitionConfigurator $definition)`
přes `rootNode()->children()`. Symfony nemá Nette `dynamic()` —
runtime hodnoty jako `%kernel.environment%` se řeší přirozeně přes container parametry v YAML.

## Service wiring (`loadExtension`)

`UlovDomovLoggingBundle::loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder)`
zrcadlí `LoggerExtension::loadConfiguration()`:

**Vždy:**
- `LoggerContext` ← `environment`, `tags`
- `LoggerContextService` ← `context`, `tracer` (jen pokud definováno), `meter` (jen pokud definováno);
  public alias `ulov_domov_logging.context_service`
- `MonologContextProcessor` s tagem `monolog.processor`

**Když `traces.url !== null || metrics.url !== null`:**
- `ContextResourceDetector` (autowired: false)
- `ResourceDetector` ← `environment`, `namespace`, `version`,
  `resourceDetectors` (= uživatelské reference + `ContextResourceDetector`)
- `OpenTelemetryClient` ← `traces.url/type`, `metrics.url/type`

**Jen když `traces.url !== null`:**
- `Tracer` ← `name`, `instance`, `OpenTelemetryClient`, `ResourceDetector`
- `TracesConsoleLogger` ← `Tracer`, tag `kernel.event_subscriber`

**Jen když `metrics.url !== null`:**
- `metricsStore` (`json` → `JsonFileMetricStore`, jinak service ID), autowired: false
- `Meter` ← `name`, `instance`, `prefix`, `OpenTelemetryClient`, `ResourceDetector`, `store`

Vynecháno oproti Nette: `tracy.logger` adapter, `MonologLoggerFactory`, Slim middleware.

## Symfony resource detektory

Tři nové třídy, `implements ResourceDetectorInterface`, návratový typ `ResourceInfo`
(vzor podle `ContextResourceDetector` a `Nette*ResourceDetector`):

| Třída | Závislost | Atributy (OTel semconv) |
|---|---|---|
| `SymfonyKernelResourceDetector` | `KernelInterface` | environment, debug flag, počet/seznam bundle |
| `SymfonyHttpResourceDetector` | `RequestStack` | `http.request.method`, `url.path`, route name, `server.address`; bezpečně prázdné při CLI (žádný request) |
| `SymfonySecurityResourceDetector` | `Security` / `TokenStorageInterface` | `user.id` (user identifier), `user.roles` |

- Detektory jsou autowire-friendly (constructor injection).
- Bundle je **nepřidává automaticky** do `resource_detectors`. Registruje je jako
  služby; uživatel si je přidá explicitně v configu
  (`resource_detectors: ['@UlovDomov\\...\\SymfonyHttpResourceDetector']`),
  stejně jako v Nette NEON. Důvod: CLI běhy nemají tahat HTTP detektor.

## Sentry

Beze změny chování oproti Nette: bundle **neregistruje** `LoggerContextIntegration`
automaticky. Třída zůstává poskytnutá v repu; uživatelé `sentry/sentry-symfony`
si ji zapojí podle pokynů v README. (`LoggerContextService` je injectable, takže
napojení je triviální.)

## Testy

`tests/Tests/Package/Symfony/`:

- `TestKernel extends Symfony\Component\HttpKernel\Kernel` — registruje
  `UlovDomovLoggingBundle` + `MonologBundle`, načítá konfiguraci z test fixtures.
- `BundleConfigTest`:
  - boot s `traces.url = null` ⇒ kontejner **neobsahuje** `Tracer`, `LoggerContextService` dostupná, `$tracer` null
  - boot s `traces.url = 'grpc://...'` ⇒ kontejner **obsahuje** `Tracer`, `TracesConsoleLogger` má tag `kernel.event_subscriber`
  - boot s `metrics.url` ⇒ kontejner obsahuje `Meter` (+ `metricsStore` při `store: json`)
  - `MonologContextProcessor` má tag `monolog.processor`
- Test konfigurace v `tests/config/symfony/` (PHP nebo YAML).

Nette testy zůstávají beze změny — Symfony testy běží paralelně.

## CI / dokumentace

- `require-dev` rozšíření (výše) automaticky pokryje CI matici (PHP 8.1–8.4),
  protože pipeline běží `composer install`.
- Krátká Symfony sekce do README (instalace bundle, příklad konfigurace,
  poznámka k resource detektorům a Sentry).

## Mimo rozsah (YAGNI)

- Tracy adapter, Slim middleware.
- `MonologLoggerFactory` v Symfony (proto i `app_log_dir` config klíč vypuštěn — neměl by konzumenta).
- Automatická aktivace Symfony resource detektorů.
- Automatická registrace Sentry integrace.
- Konfigurovatelnost MonologLoggerFactory (přidá se, až bude reálná potřeba).
