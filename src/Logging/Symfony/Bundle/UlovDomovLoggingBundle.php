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
    }
}
