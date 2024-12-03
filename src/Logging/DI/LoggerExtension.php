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
use UlovDomov\Logging\TracyLogger;

final class LoggerExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'environment' => Expect::string()->dynamic()->nullable(),
            'appLogDir' => Expect::string(Debugger::$logDirectory . \DIRECTORY_SEPARATOR . 'app')->dynamic(),
            'tags' => Expect::arrayOf(Expect::string()->dynamic(), Expect::string()),
        ])->castTo('array');
    }

    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        /** @var array<string> $config */
        $config = $this->config;

        $container->addDefinition($this->prefix('contextService'))
            ->setFactory(LoggerContextService::class, [
                'environment' => $config['environment'],
                'tags' => $config['tags'],
            ]);

        $existing = 'tracy.logger';

        if ($container->hasDefinition($existing)) {
            $definition = $container->addDefinition($this->prefix('tracyAdapter'));
            $definition->setType(ILogger::class)
                ->setFactory(TracyLogger::class);
            $container->removeDefinition($existing);
            $container->addAlias($existing, $this->prefix('tracyAdapter'));
        }

        if (\class_exists(ProcessorInterface::class)) {
            $container->addDefinition($this->prefix('monologProcessor'))
                ->setFactory(MonologContextProcessor::class);

            $container->addDefinition($this->prefix('monologFactory'))
                ->setFactory(MonologLoggerFactory::class, [
                    'logDir' => $config['appLogDir'],
                ]);
        }
    }
}
