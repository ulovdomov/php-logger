<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

use UlovDomov\Exceptions\LogicException;
use UlovDomov\Logging\OpenTelemetry\Metrics\Meter;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;

final class LoggerContextService
{
    public function __construct(
        private readonly LoggerContext $context,
        private readonly Tracer|null $tracer = null,
        private readonly Meter|null $meter = null,
    )
    {
    }

    public function getEnvironment(): string|null
    {
        return $this->context->environment;
    }

    public function getIpAddress(): string|null
    {
        return $this->context->getIpAddress();
    }

    public function setUser(string|int $id, string|null $email = null, string|null $username = null): void
    {
        $this->context->setUser($id, $email, $username);
    }

    /**
     * @return array<string|int>
     */
    public function getUserData(): array
    {
        $result = [];
        $ip = $this->getIpAddress();

        if ($ip !== null) {
            $result['ip_address'] = $ip;
        }

        $userId = $this->context->getUserId();

        if ($userId !== null) {
            $result['id'] = $userId;
        }

        $userId = $this->context->getUserEmail();

        if ($userId !== null) {
            $result['email'] = $userId;
        }

        $username = $this->context->getUsername();

        if ($username !== null) {
            $result['username'] = $username;
        }

        return $result;
    }

    public function addTag(string $name, string|int|float $value): void
    {
        if ($this->tracer !== null && $this->tracer->isEnabled()) {
            $this->tracer->getCurrent()->setAttribute('tags.' . $name, $value);
        }

        $this->context->addTag($name, $value);
    }

    public function removeTag(string $name): void
    {
        $this->context->removeTag($name);
    }

    /**
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->context->getTags();
    }

    public function addContext(string|int $key, mixed $value): void
    {
        $this->context->addContext($key, $value);

        if ($this->tracer !== null && $this->tracer->isEnabled()) {
            $this->tracer->getCurrent()->setAttribute('context.attributes', $this->context->getContext());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context->getContext();
    }

    /**
     * @param array<mixed> $context
     */
    public function setContext(array $context): void
    {
        if ($this->tracer !== null && $this->tracer->isEnabled()) {
            $this->tracer->getCurrent()->setAttribute('context.attributes', $context);
        }

        $this->context->setContext($context);
    }

    /**
     * @deprecated use {@link getTraceId()}
     */
    public function getProcessId(): string
    {
        return $this->getTraceId();
    }

    public function getTraceId(): string
    {
        if ($this->tracer !== null && $this->tracer->isEnabled()) {
            return $this->tracer->getCurrent()->getContext()->getTraceId();
        }

        return $this->context->getTraceId();
    }

    public function getSpanId(): string
    {
        if ($this->tracer !== null && $this->tracer->isEnabled()) {
            return $this->tracer->getCurrent()->getContext()->getSpanId();
        }

        return $this->context->getSpanId();
    }

    /**
     * @return array<string>
     */
    public function getTraceInfo(): array
    {
        return [
            'trace_id' => \str_replace('-', '', $this->getTraceId()),
            'span_id' => $this->getSpanId(),
            'status' => 'default',
        ];
    }

    public function getTracer(): Tracer
    {
        if ($this->tracer === null) {
            throw LogicException::create('Open Telemetry tracer is not configured.');
        }

        return $this->tracer;
    }

    public function getMeter(): Meter
    {
        if ($this->meter === null) {
            throw LogicException::create('Open Telemetry metrics are not configured.');
        }

        return $this->meter;
    }
}
