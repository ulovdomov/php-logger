<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\HttpKernel\KernelInterface;

final class SymfonyKernelResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function getResource(): ResourceInfo
    {
        $attributes = [
            'symfony.kernel.environment' => $this->kernel->getEnvironment(),
            'symfony.kernel.debug' => $this->kernel->isDebug(),
            'symfony.kernel.bundles' => \array_keys($this->kernel->getBundles()),
        ];

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
