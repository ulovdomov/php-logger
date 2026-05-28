<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\HttpFoundation\RequestStack;

final class SymfonyHttpResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getResource(): ResourceInfo
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return ResourceInfo::emptyResource();
        }

        $attributes = [];
        $attributes['http.request.method'] = $request->getMethod();

        $queryString = $request->getQueryString();

        if ($queryString !== null && $queryString !== '') {
            $attributes['http.request.query_string'] = $queryString;
        }

        $attributes['http.request.url'] = $request->getSchemeAndHttpHost() . $request->getPathInfo();

        $route = $request->attributes->get('_route');

        if (\is_string($route)) {
            $attributes['http.route'] = $route;
        }

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
