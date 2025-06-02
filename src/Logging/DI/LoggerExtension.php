<?php declare(strict_types = 1);

namespace UlovDomov\Logging\DI;

use Monolog\Processor\ProcessorInterface;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Tracy\Debugger;
use Tracy\ILogger;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\Monolog\MonologContextProcessor;
use UlovDomov\Logging\Monolog\MonologLoggerFactory;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;
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
                'traces' => Expect::structure([
                    'name' => Expect::string()->dynamic()->default('ud-php-app'),
                    'url' => Expect::string()->dynamic()->nullable(),
                    'type' => Expect::anyOf(
                        TransportType::File->value,
                        TransportType::Grpc->value,
                        TransportType::Http->value,
                        TransportType::HttpProtobuf->value,
                    )->default(
                        TransportType::Grpc->value,
                    ),
                ])->castTo('array'),
            ])->castTo('array'),
        ])->castTo('array');
    }

    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        /**
         * @var array{
         *          environment: string,
         *          appLogDir: string,
         *          tags: array<string, string>,
         *          openTelemetry: array{traces: array{name:string, url: string|null, type: string}}
         *     } $config
         */
        $config = $this->config;

        $openTelemetryLoaded = $this->loadOpenTelemetry($config['openTelemetry']);

        $container->addDefinition($this->prefix('contextService'))
            ->setFactory(LoggerContextService::class, [
                'environment' => $config['environment'],
                'tags' => $config['tags'],
                'tracer' => $openTelemetryLoaded ? $this->prefix('@tracer') : null,
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
     * @param array{traces: array{name:string, url: string|null, type: string}} $config
     */
    private function loadOpenTelemetry(array $config): bool
    {
        $container = $this->getContainerBuilder();

        if ($config['traces']['url'] !== null) {
            $container->addDefinition($this->prefix('openTelemetryClient'))
                ->setAutowired(false)
                ->setFactory(OpenTelemetryClient::class, [
                    'url' => $config['traces']['url'],
                    'type' => TransportType::from($config['traces']['type']),
                ]);

            $container->addDefinition($this->prefix('tracer'))
                ->setFactory(Tracer::class, [
                    'name' => $config['traces']['name'],
                    'openTelemetryClient' => $this->prefix('@openTelemetryClient'),
                ]);

            return true;
        }

        return false;
    }
}
