<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use UlovDomov\Logging\LoggerContext;

final class ContextResourceDetector implements ResourceDetectorInterface
{
    public function __construct(private readonly LoggerContext $context)
    {
    }

    public function getResource(): ResourceInfo
    {
        $attributes = [];

        $context = $this->context->getContext();

        if (\count($context) > 0) {
            $attributes['context.attributes'] = $context;
        }

        foreach ($this->context->getTags() as $name => $value) {
            $attributes['tags.' . $name] = $value;
        }

        $email = $this->context->getUserEmail();

        if ($email !== null) {
            $attributes['user.email'] = $email;
        }

        $userId = $this->context->getUserId();

        if ($userId !== null) {
            $attributes['user.id'] = $userId;
        }

        $username = $this->context->getUsername();

        if ($username !== null) {
            $attributes['user.username'] = $username;
        }

        $ipAddress = $this->context->getIpAddress();

        if ($ipAddress !== null) {
            $attributes['user.ip_address'] = $ipAddress;
        }

        return ResourceInfo::create(Attributes::create($attributes));
    }
}
