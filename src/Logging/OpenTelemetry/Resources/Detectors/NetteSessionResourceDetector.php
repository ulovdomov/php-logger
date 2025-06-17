<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use Nette\DI\Container;
use Nette\Http\Session;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

final class NetteSessionResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getResource(): ResourceInfo
    {
        $attributes = [];

        $session = $this->container->getByType(Session::class, false);

        // There is no session or closed session
        if (!$session instanceof Session || !$session->isStarted()) {
            return ResourceInfo::emptyResource();
        }

        // @see https://github.com/nette/http/blob/v3.1/src/Http/Session.php
        $sessionData = $_SESSION['__NF']['DATA'] ?? [];

        /** @var array<mixed, string> $iterator */
        $iterator = new \ArrayIterator(\array_keys($sessionData));
        $data = [];

        foreach ($iterator as $section) {
            $data[$section] = \iterator_to_array($session->getSection($section)->getIterator());
        }

        $attributes['nette.session.data'] = $data;

        if (\PHP_SAPI !== 'cli') {
            $attributes['nette.session.name'] = $session->getName();
            $attributes['nette.session.id'] = $session->getId();
        }

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
