<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Traces;

use OpenTelemetry\API\Trace\Span as OpenTelemetrySpan;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

final class Span implements SpanInterface
{
    public function __construct(private readonly SpanInterface $span)
    {

    }

    public static function fromContext(ContextInterface $context): Span
    {
        return new self(OpenTelemetrySpan::fromContext($context));
    }

    public static function getCurrent(): Span
    {
        return new self(OpenTelemetrySpan::getCurrent());
    }

    public static function getInvalid(): Span
    {
        return new self(OpenTelemetrySpan::getInvalid());
    }

    public static function wrap(SpanContextInterface $spanContext): Span
    {
        return new self(OpenTelemetrySpan::wrap($spanContext));
    }

    public function getContext(): SpanContextInterface
    {
        return $this->span->getContext();
    }

    public function isRecording(): bool
    {
        return $this->span->isRecording();
    }

    /**
     * @param bool|int|float|string|array<mixed>|null $value
     */
    public function setAttribute(string $key, bool|int|float|string|array|null $value): Span
    {
        $this->span->setAttribute($key, $value);

        return $this;
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function setAttributes(iterable $attributes): Span
    {
        $this->span->setAttributes($attributes);

        return $this;
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function addLink(SpanContextInterface $context, iterable $attributes = []): Span
    {
        $this->span->addLink($context, $attributes);

        return $this;
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function addEvent(string $name, iterable $attributes = [], int|null $timestamp = null): Span
    {
        $this->span->addEvent($name, $attributes, $timestamp);

        return $this;
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function recordException(Throwable $exception, iterable $attributes = []): Span
    {
        $this->span->recordException($exception, $attributes);

        return $this;
    }

    public function updateName(string $name): Span
    {
        $this->span->updateName($name);

        return $this;
    }

    public function setStatus(string $code, string|null $description = null): Span
    {
        $this->span->setStatus($code, $description);

        return $this;
    }

    public function end(int|null $endEpochNanos = null): void
    {
        $this->span->end($endEpochNanos);
    }

    public function activate(): ScopeInterface
    {
        return $this->span->activate();
    }

    public function storeInContext(ContextInterface $context): ContextInterface
    {
        return $this->span->storeInContext($context);
    }

    public function equals(self $self): bool
    {
        return $this->span === $self->span;
    }
}
