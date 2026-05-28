<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
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
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', \sys_get_temp_dir());

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

    /**
     * @throws OutOfBoundsException
     */
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

    /**
     * @throws OutOfBoundsException
     */
    public function testDefaultsAppliedWithEmptyConfig(): void
    {
        $builder = $this->buildContainer([]);

        $context = $builder->getDefinition(LoggerContext::class);

        self::assertSame([], $context->getArgument('$tags'));
        self::assertNull($context->getArgument('$environment'));
    }

    public function testTracerAndMeterAbsentWhenNoUrls(): void
    {
        $builder = $this->buildContainer([
            'open_telemetry' => ['name' => 'svc'],
        ]);

        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Traces\Tracer::class));
        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Metrics\Meter::class));
        self::assertFalse($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient::class));
    }

    /**
     * @throws OutOfBoundsException
     */
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
        self::assertTrue(
            $builder->hasDefinition(
                \UlovDomov\Logging\OpenTelemetry\Resources\Detectors\ContextResourceDetector::class,
            ),
        );
        self::assertTrue($builder->hasDefinition(\UlovDomov\Logging\OpenTelemetry\Traces\Tracer::class));

        $console = $builder->getDefinition(\UlovDomov\Logging\Console\TracesConsoleLogger::class);
        self::assertArrayHasKey('kernel.event_subscriber', $console->getTags());

        $contextService = $builder->getDefinition(LoggerContextService::class);
        self::assertInstanceOf(
            \Symfony\Component\DependencyInjection\Reference::class,
            $contextService->getArgument('$tracer'),
        );
    }

    /**
     * @throws OutOfBoundsException
     */
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
        self::assertInstanceOf(
            \Symfony\Component\DependencyInjection\Reference::class,
            $contextService->getArgument('$meter'),
        );
    }
}
