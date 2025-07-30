<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Traces;

use OpenTelemetry\API\Trace\Span as OpenTelemetrySpan;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;
use UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector;

final class Tracer
{
    private TracerProvider|null $provider = null;
    private TracerInterface|null $tracer = null;
    private Span|null $root = null;
    private ScopeInterface|null $scope = null;

    public function __construct(
        private readonly string $name,
        private readonly string|null $instance,
        private readonly OpenTelemetryClient $openTelemetryClient,
        private readonly ResourceDetector $resourceDetector,
    )
    {

    }

    public function isEnabled(): bool
    {
        return $this->root !== null;
    }

    /**
     * @param array<non-empty-string, mixed> $attributes
     */
    public function enable(array $attributes = []): void
    {
        $name = 'root';
        $this->root = new Span($this->getTracer()->spanBuilder($name)->setAttributes($attributes)->startSpan());
        $this->scope = $this->root->activate();
    }

    /**
     * @param non-empty-string $name
     * @param iterable<non-empty-string, mixed> $attributes
     */
    public function startSpan(string $name, iterable $attributes = []): Span
    {
        if (!$this->isEnabled()) {
            throw new \LogicException('Tracer is not enabled. Call enable() first.');
        }

        return new Span($this->getTracer()->spanBuilder($name)->setAttributes($attributes)->startSpan());
    }

    public function end(): void
    {
        $this->getScope()->detach();
        $this->getRoot()->end();

        $this->getProvider()->shutdown();

        $this->root = null;
        $this->scope = null;
    }

    public function forceFlush(): void
    {
        if (!$this->isEnabled()) {
            throw new \LogicException('Tracer is not enabled. Call enable() first.');
        }

        $this->getProvider()->forceFlush();
    }

    public function getCurrent(): Span
    {
        return new Span(OpenTelemetrySpan::getCurrent());
    }

    public function getRoot(): Span
    {
        if ($this->root === null) {
            throw new \LogicException('Tracer is not enabled. Call enable() first.');
        }

        return $this->root;
    }

    private function getScope(): ScopeInterface
    {
        if ($this->scope === null) {
            throw new \LogicException('Tracer is not enabled. Call enable() first.');
        }

        return $this->scope;
    }

    private function getProvider(): TracerProvider
    {
        if ($this->provider === null) {
            $exporter = new SpanExporter($this->openTelemetryClient->getTraceTransport());
            $this->provider = new TracerProvider(
                new SimpleSpanProcessor($exporter), // can use BatchSpanProcessor
                resource: $this->resourceDetector->getResource($this->name, $this->instance),
            );
        }

        return $this->provider;
    }

    private function getTracer(): TracerInterface
    {
        if ($this->tracer === null) {
            $this->tracer = $this->getProvider()->getTracer($this->name);
        }

        return $this->tracer;
    }
}
