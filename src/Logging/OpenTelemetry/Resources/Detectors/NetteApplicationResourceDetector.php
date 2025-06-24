<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use Nette\Application\Application;
use Nette\Application\Request;
use Nette\DI\Container;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

final class NetteApplicationResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getResource(): ResourceInfo
    {
        $attributes = [];

        $application = $this->container->getByType(Application::class, false);

        // There is no application
        if (!$application instanceof Application) {
            return ResourceInfo::emptyResource();
        }

        foreach ($application->getRequests() as $n => $request) {
            $data = [
                'method' => $request->getMethod(),
                'presenter' => $request->getPresenterName(),
                'params' => $request->getParameters(),
            ];

            if ($request->hasFlag(Request::VARYING)) {
                $data['flag'] = Request::VARYING;
            } elseif ($request->hasFlag(Request::RESTORED)) {
                $data['flag'] = Request::RESTORED;
            }

            $attributes['nette.application.request'] ??= [];
            $attributes['nette.application.request'][$n] = $data;
        }

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
