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
}
