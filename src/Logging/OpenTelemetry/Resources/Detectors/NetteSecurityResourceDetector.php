<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use Nette\DI\Container;
use Nette\Http\Session;
use Nette\Security\IIdentity;
use Nette\Security\User;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

final class NetteSecurityResourceDetector implements ResourceDetectorInterface
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

        $user = $this->container->getByType(User::class, false);

        // There is no user service
        if (!$user instanceof User || !$user->isLoggedIn()) {
            return ResourceInfo::emptyResource();
        }

        $identity = $user->getIdentity();

        // Anonymous user
        if (!($identity instanceof IIdentity)) {
            return ResourceInfo::emptyResource();
        }

        $attributes['nette.security.id'] = \strval($identity->getId());
        $attributes['nette.security.email'] = $identity->getData()['email'] ?? null;
        $attributes['nette.security.username'] = $identity->getData()['username'] ?? null;
        $attributes['nette.security.roles'] = $identity->getRoles();
        $attributes['nette.security.data'] = $identity->getData();

        return ResourceInfo::create(Attributes::create(\array_filter($attributes)), ResourceAttributes::SCHEMA_URL);
    }
}
