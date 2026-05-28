<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SymfonySecurityResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function getResource(): ResourceInfo
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return ResourceInfo::emptyResource();
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return ResourceInfo::emptyResource();
        }

        $attributes = [
            'symfony.security.id' => $user->getUserIdentifier(),
            'symfony.security.roles' => $user->getRoles(),
        ];

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
