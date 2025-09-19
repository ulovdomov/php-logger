<?php declare(strict_types = 1);

namespace UlovDomov\Logging\DI;

use Monolog\Processor\ProcessorInterface;
use Nette\DI\CompilerExtension;
use Nette\Schema\DynamicParameter;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use Tracy\Debugger;
use Tracy\ILogger;
use UlovDomov\Logging\LoggerContext;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\Monolog\MonologContextProcessor;
use UlovDomov\Logging\Monolog\MonologLoggerFactory;
use UlovDomov\Logging\OpenTelemetry\Metrics\Meter;
use UlovDomov\Logging\OpenTelemetry\Metrics\Store\JsonFileMetricStore;
use UlovDomov\Logging\OpenTelemetry\Metrics\Store\MetricValueStore;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\ContextResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;
use UlovDomov\Logging\OpenTelemetry\TransportType;
use UlovDomov\Logging\TracyLogger;

final class LoggerExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'environment' => Expect::string()->dynamic()->nullable(),
            'appLogDir' => Expect::string(Debugger::$logDirectory . \DIRECTORY_SEPARATOR . 'app')->dynamic(),
            'tags' => Expect::arrayOf(Expect::string()->dynamic(), Expect::string()),
            'openTelemetry' => Expect::structure([
                'name' => Expect::string()->dynamic()->default('unknown-ud-app'),
                'instance' => Expect::string()->dynamic()->nullable(),
                'version' => Expect::string()->dynamic()->default('0.0.0'),
                'namespace' => Expect::string()->dynamic()->default('ud-php-app'),
                'resourceDetectors' => Expect::array()->default([]),
                'traces' => Expect::structure([
                    'url' => Expect::string()->dynamic()->nullable(),
                    'type' => Expect::anyOf(
                        TransportType::File->value,
                        TransportType::Grpc->value,
                        TransportType::Http->value,
                        TransportType::HttpProtobuf->value,
                        TransportType::Null->value,
                    )->default(
                        TransportType::Grpc->value,
                    ),
                ])->castTo('array'),
                'metrics' => Expect::structure([
                    'url' => Expect::string()->dynamic()->nullable(),
                    'prefix' => Expect::string()->default('udapp'),
                    'type' => Expect::anyOf(
                        TransportType::File->value,
                        TransportType::Grpc->value,
                        TransportType::Http->value,
                        TransportType::HttpProtobuf->value,
                        TransportType::Null->value,
                    )->default(
                        TransportType::Grpc->value,
                    ),
                    'store' => Expect::anyOf(
                        'json',
                        Expect::type(MetricValueStore::class),
                    )->dynamic()->nullable(),
                ])->castTo('array'),
            ])->castTo('array'),
        ])->castTo('array');
    }

    public function loadConfiguration(): void
    {
        /**
         * @var array{
         *          environment: string,
         *          appLogDir: string,
         *          tags: array<string, string>,
         *          openTelemetry: array{
         *              name:string,
         *              instance:string|null,
         *              version:string,
         *              namespace:string,
         *              resourceDetectors:array<ResourceDetectorInterface>,
         *              traces: array{url: string|null, type: string},
         *              metrics: array{url: string|null, type: string, prefix: string, store: string|null},
         *          }
         *     } $config
         */
        $config = $this->config;
        $container = $this->getContainerBuilder();

        $environment = $config['environment'];
        $this->loadOpenTelemetry($environment, $config['openTelemetry']);

        $container->addDefinition($this->prefix('context'))
            ->setFactory(LoggerContext::class, [
                'environment' => $environment,
                'tags' => $config['tags'],
            ]);

        $container->addDefinition($this->prefix('contextService'))
            ->setFactory(LoggerContextService::class, [
                'context' => $this->prefix('@context'),
                'tracer' => $container->hasDefinition($this->prefix('tracer')) ? $this->prefix('@tracer') : null,
                'meter' => $container->hasDefinition($this->prefix('meter')) ? $this->prefix('@meter') : null,
            ]);

        $existing = 'tracy.logger';

        if ($container->hasDefinition($existing)) {
            $definition = $container->addDefinition($this->prefix('tracyAdapter'));
            $definition->setType(ILogger::class)
                ->setFactory(TracyLogger::class);
            $container->removeDefinition($existing);
            $container->addAlias($existing, $this->prefix('tracyAdapter'));
        }

        if (\interface_exists(ProcessorInterface::class)) {
            $container->addDefinition($this->prefix('monologProcessor'))
                ->setFactory(MonologContextProcessor::class);

            $container->addDefinition($this->prefix('monologFactory'))
                ->setFactory(MonologLoggerFactory::class, [
                    'logDir' => $config['appLogDir'],
                ]);
        }
    }

    /**
     * @param array{
     *          name:string,
     *          instance:string|null,
     *          version:string,
     *          namespace:string,
     *          resourceDetectors:array<ResourceDetectorInterface>,
     *          traces: array{url: string|null, type: string},
     *          metrics: array{url: string|null, type: string, prefix: string, store: string|null},
     *        } $config
     */
    private function loadOpenTelemetry(DynamicParameter|string $environment, array $config): void
    {
        $container = $this->getContainerBuilder();

        if (
            ($config['traces']['url'] !== null && \strlen(\trim($config['traces']['url'])) > 0)
            || ($config['metrics']['url'] !== null && \strlen(\trim($config['metrics']['url'])) > 0)
        ) {
            $container->addDefinition($this->prefix('contextResourceDetector'))
                ->setAutowired(false)
                ->setFactory(ContextResourceDetector::class);

            $resourceDetectors = $config['resourceDetectors'];
            $resourceDetectors[] = $this->prefix('@contextResourceDetector');

            $container->addDefinition($this->prefix('resourceDetector'))
                ->setAutowired(false)
                ->setFactory(ResourceDetector::class, [
                    'environment' => $environment,
                    'version' => $config['version'],
                    'namespace' => $config['namespace'],
                    'resourceDetectors' => $resourceDetectors,
                ]);

            $container->addDefinition($this->prefix('openTelemetryClient'))
                ->setAutowired(false)
                ->setFactory(OpenTelemetryClient::class, [
                    'url' => $config['traces']['url'],
                    'type' => TransportType::from($config['traces']['type']),
                    'metricsUrl' => $config['metrics']['url'],
                    'metricsType' => TransportType::from($config['metrics']['type']),
                ]);
        }

        if ($config['traces']['url'] !== null && \strlen(\trim($config['traces']['url'])) > 0) {
            $container->addDefinition($this->prefix('tracer'))
                ->setFactory(Tracer::class, [
                    'name' => $config['name'],
                    'instance' => $config['instance'],
                    'openTelemetryClient' => $this->prefix('@openTelemetryClient'),
                    'resourceDetector' => $this->prefix('@resourceDetector'),
                ]);
        }

        if ($config['metrics']['url'] !== null && \strlen(\trim($config['metrics']['url'])) > 0) {
            $store = $config['metrics']['store'];

            if ($store !== null) {
                $container->addDefinition($this->prefix('metricsStore'))
                    ->setAutowired(false)
                    ->setFactory(match ($store) {
                        'json' => JsonFileMetricStore::class,
                        default => $store,
                    });
            }

            $container->addDefinition($this->prefix('meter'))
                ->setFactory(Meter::class, [
                    'name' => $config['name'],
                    'instance' => $config['instance'],
                    'prefix' => $config['metrics']['prefix'],
                    'openTelemetryClient' => $this->prefix('@openTelemetryClient'),
                    'resourceDetector' => $this->prefix('@resourceDetector'),
                    'store' => $store !== null ? $this->prefix('@metricsStore') : null,
                ]);
        }
    }
}
