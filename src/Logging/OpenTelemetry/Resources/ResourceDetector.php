<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\Detectors\Composite;
use OpenTelemetry\SDK\Resource\Detectors\Environment;
use OpenTelemetry\SDK\Resource\Detectors\Host;
use OpenTelemetry\SDK\Resource\Detectors\Sdk;
use OpenTelemetry\SDK\Resource\Detectors\Service;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

final class ResourceDetector
{
    private ResourceInfo|null $resource = null;

    /**
     * @param array<ResourceDetectorInterface> $resourceDetectors
     */
    public function __construct(
        private readonly string $environment,
        private readonly string $namespace,
        private readonly string $version,
        private readonly array $resourceDetectors,
    )
    {
    }

    public function getResource(string $name, string|null $instance): ResourceInfo
    {
        if ($this->resource === null) {
            $resource = self::getDefaultDetectors()->merge($this->createDefaultResource($name, $instance));

            foreach ($this->resourceDetectors as $detector) {
                $resource = $resource->merge($detector->getResource());
            }

            $this->resource = $resource;
        }

        return $this->resource;
    }

    private function createDefaultResource(string $name, string|null $instance): ResourceInfo
    {
        $attributes = [
            ResourceAttributes::SERVICE_NAMESPACE => $this->namespace,
            ResourceAttributes::SERVICE_NAME => $name,
            ResourceAttributes::SERVICE_VERSION => $this->version,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $this->environment,
            ResourceAttributes::PROCESS_RUNTIME_NAME => \php_sapi_name(),
            ResourceAttributes::PROCESS_RUNTIME_VERSION => \PHP_VERSION,
            ResourceAttributes::PROCESS_PID => \getmypid(),
            ResourceAttributes::PROCESS_EXECUTABLE_PATH => \PHP_BINARY,
        ];

        if (isset($_SERVER['argv'])) {
            $attributes[ResourceAttributes::PROCESS_COMMAND] = $_SERVER['argv'][0] ?? 'unknown';
            $attributes[ResourceAttributes::PROCESS_COMMAND_ARGS] = $_SERVER['argv'];
        }

        if ($instance !== null) {
            $attributes[ResourceAttributes::SERVICE_INSTANCE_ID] = $instance;
        }

        return ResourceInfo::create(Attributes::create($attributes));
    }

    private static function getDefaultDetectors(): ResourceInfo
    {
        return (new Composite([
            new Host(),
            new Environment(),
            new Sdk(),
            new Service(),
        ]))->getResource();
    }
}
