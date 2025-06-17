<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use Nette\DI\Container;
use Nette\Http\IRequest;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

final class NetteHttpResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getResource(): ResourceInfo
    {
        $attributes = [];

        $httpRequest = $this->container->getByType(IRequest::class, false);

        // There is no http request
        if (!$httpRequest instanceof IRequest) {
            return ResourceInfo::emptyResource();
        }

        $attributes['http.request.url'] = $httpRequest->getUrl()->__toString();
        $attributes['http.request.method'] = $httpRequest->getMethod();

        if ($httpRequest->getUrl()->getQuery() !== '') {
            $attributes['http.request.query_string'] = $httpRequest->getUrl()->getQuery();
        }

        $attributes['http.request.cookies'] = $httpRequest->getCookies();
        $attributes['http.request.headers'] = $httpRequest->getHeaders();

        $body = $httpRequest->getRawBody();

        if ($body !== null && $body !== '') {
            $attributes['http.request.data'] = $body;
        }

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
