<?php declare(strict_types = 1);

namespace Tests\Package;

use Nette\Bootstrap\Configurator;
use Nette\DI\MissingServiceException;
use Tests\Libraries\TestBootstrap;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\OpenTelemetry\Metrics\Meter;
use UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;
use UlovDomov\TestExtras\TestCases\BaseDITestCase;

require_once __DIR__ . '/../../../vendor/autoload.php';

final class DITest extends BaseDITestCase
{
    public function testTracer(): void
    {
        $tracer = $this->getService(Tracer::class);

        self::assertInstanceOf(Tracer::class, $tracer);
    }

    public function testMeter(): void
    {
        $meter = $this->getService(Meter::class);

        self::assertInstanceOf(Meter::class, $meter);
    }

    /**
     * @throws MissingServiceException
     */
    public function testResourceDetector(): void
    {
        /** @var ResourceDetector $detector */
        $detector = $this->getContainer()->getService('testLogger.resourceDetector');

        $resource = $detector->getResource('test-app');
        $attributes = $resource->getAttributes();

        self::assertSame('test-app', $attributes->get('service.name'));
        self::assertSame('test-namespace', $attributes->get('service.namespace'));
        self::assertSame('1.0.0@beta', $attributes->get('service.version'));
        self::assertSame('test', $attributes->get('deployment.environment.name'));
        self::assertSame('logger-tests', $attributes->get('tags.app'));
        self::assertIsNumeric($attributes->get('process.pid'));
    }

    /**
     * @throws MissingServiceException
     */
    public function testResourceDetectorUser(): void
    {
        /** @var LoggerContextService $contextService */
        $contextService = $this->getService(LoggerContextService::class);
        $contextService->setUser('1b44ddb4-9040-456b-a6b6-359c3832300d', 'test@example.com', 'john');

        /** @var ResourceDetector $detector */
        $detector = $this->getContainer()->getService('testLogger.resourceDetector');

        $resource = $detector->getResource('test-app');
        $attributes = $resource->getAttributes();

        self::assertSame('test@example.com', $attributes->get('user.email'));
        self::assertSame('1b44ddb4-9040-456b-a6b6-359c3832300d', $attributes->get('user.id'));
        self::assertSame('john', $attributes->get('user.username'));
    }

    protected function createConfigurator(): Configurator
    {
        return TestBootstrap::boot();
    }
}
