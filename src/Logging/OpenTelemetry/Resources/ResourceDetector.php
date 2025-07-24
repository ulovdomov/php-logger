<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
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
            $resource = ResourceInfoFactory::emptyResource();
            $resource = $resource->merge(ResourceInfoFactory::defaultResource());
            $resource = $resource->merge($this->createDefaultResource($name, $instance));

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
        ];

        if ($instance !== null) {
            $attributes[ResourceAttributes::SERVICE_INSTANCE_ID] = $instance;
        }

        return ResourceInfo::create(Attributes::create($attributes));
    }
}
