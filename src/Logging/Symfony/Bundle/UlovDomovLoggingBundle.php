<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Symfony\Bundle;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use UlovDomov\Logging\Console\TracesConsoleLogger;
use UlovDomov\Logging\LoggerContext;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\Monolog\MonologContextProcessor;
use UlovDomov\Logging\OpenTelemetry\Metrics\Meter;
use UlovDomov\Logging\OpenTelemetry\Metrics\Store\JsonFileMetricStore;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\ContextResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyHttpResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyKernelResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonySecurityResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;
use UlovDomov\Logging\OpenTelemetry\TransportType;

final class UlovDomovLoggingBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $transportTypes = ['grpc', 'http', 'http-protobuf', 'file', 'null'];

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();
        $root = $rootNode->children();

        $root->scalarNode('environment')->defaultNull();
        $root->arrayNode('tags')->defaultValue([])->scalarPrototype();

        $openTelemetry = $root->arrayNode('open_telemetry')->addDefaultsIfNotSet()->children();
        $openTelemetry->scalarNode('name')->defaultValue('unknown-ud-app');
        $openTelemetry->scalarNode('instance')->defaultNull();
        $openTelemetry->scalarNode('version')->defaultValue('0.0.0');
        $openTelemetry->scalarNode('namespace')->defaultValue('ud-php-app');
        $openTelemetry->arrayNode('resource_detectors')->defaultValue([])->scalarPrototype();

        $traces = $openTelemetry->arrayNode('traces')->addDefaultsIfNotSet()->children();
        $traces->scalarNode('url')->defaultNull();
        $traces->enumNode('type')->values($transportTypes)->defaultValue('grpc');

        $metrics = $openTelemetry->arrayNode('metrics')->addDefaultsIfNotSet()->children();
        $metrics->scalarNode('url')->defaultNull();
        $metrics->scalarNode('prefix')->defaultValue('udapp');
        $metrics->enumNode('type')->values($transportTypes)->defaultValue('grpc');
        $metrics->scalarNode('store')->defaultNull();
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
    // @phpstan-ignore-next-line — narrowing array param from ConfigurableExtensionInterface/AbstractBundle is intentional
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

        foreach ([SymfonyKernelResourceDetector::class, SymfonyHttpResourceDetector::class, SymfonySecurityResourceDetector::class] as $detectorClass) {
            $detectorDef = new Definition($detectorClass);
            $detectorDef->setAutowired(true);
            $builder->setDefinition($detectorClass, $detectorDef);
        }

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
    }
}
