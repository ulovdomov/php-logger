<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyKernelResourceDetector;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class SymfonyKernelResourceDetectorTest extends TestCase
{
    /**
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    public function testCollectsKernelMetadata(): void
    {
        $bundle = $this->createMock(BundleInterface::class);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $kernel->method('isDebug')->willReturn(false);
        $kernel->method('getBundles')->willReturn(['AcmeBundle' => $bundle]);

        $detector = new SymfonyKernelResourceDetector($kernel);
        $attributes = $detector->getResource()->getAttributes();

        self::assertSame('prod', $attributes->get('symfony.kernel.environment'));
        self::assertFalse($attributes->get('symfony.kernel.debug'));
        self::assertSame(['AcmeBundle'], $attributes->get('symfony.kernel.bundles'));
    }
}
