<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Tests\Package\Symfony\Kernel\TestKernel;
use UlovDomov\Logging\LoggerContextService;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class BundleBootTest extends TestCase
{
    /**
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testKernelBootsAndExposesContextService(): void
    {
        $kernel = new TestKernel([
            'environment' => 'test',
            'tags' => ['app' => 'boot-test'],
        ]);
        $kernel->boot();

        try {
            $contextService = $kernel->getContainer()->get('ulov_domov_logging.context_service');

            self::assertInstanceOf(LoggerContextService::class, $contextService);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testKernelCompilesWithOpenTelemetryEnabled(): void
    {
        $kernel = new TestKernel([
            'environment' => 'test',
            'open_telemetry' => [
                'name' => 'boot-test',
                'traces' => ['url' => 'http://collector:4317', 'type' => 'grpc'],
                'metrics' => ['url' => 'http://collector:4317', 'type' => 'grpc'],
            ],
        ]);
        $kernel->boot();

        try {
            $contextService = $kernel->getContainer()->get('ulov_domov_logging.context_service');

            self::assertInstanceOf(LoggerContextService::class, $contextService);
        } finally {
            $kernel->shutdown();
        }
    }
}
