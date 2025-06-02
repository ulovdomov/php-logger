<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Traces;

use OpenTelemetry\API\Trace\Span as OpenTelemetrySpan;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use UlovDomov\Logging\LoggerContextService;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;

final class Tracer
{
    private const ATTRIBUTE_CONTEXT = 'context';
    private const ATTRIBUTE_TAGS = 'tags';
    private const ATTRIBUTE_USER = 'user';

    private TracerProvider $provider;
    private TracerInterface $tracer;
    private Span|null $root = null;
    private ScopeInterface|null $scope = null;
    private LoggerContextService|null $contextService = null;

    public function __construct(string $name, OpenTelemetryClient $openTelemetryClient)
    {
        $exporter = new SpanExporter($openTelemetryClient->getTransport());
        $this->provider = new TracerProvider(new SimpleSpanProcessor($exporter)); // can use BatchSpanProcessor
        $this->tracer = $this->provider->getTracer($name);
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
        if ($this->contextService !== null) {
            $attributes = array_merge($attributes, [
                self::ATTRIBUTE_USER => $this->contextService->getUserData(),
                self::ATTRIBUTE_TAGS => $this->contextService->getTags(),
                self::ATTRIBUTE_CONTEXT => $this->contextService->getContext(),
            ]);
        }

        $name = 'root';
        $this->root = new Span($this->tracer->spanBuilder($name)->setAttributes($attributes)->startSpan());
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

        return new Span($this->tracer->spanBuilder($name)->setAttributes($attributes)->startSpan());
    }

    public function end(): void
    {
        $this->getRoot()->end();
        $this->getScope()->detach();

        $this->provider->shutdown();

        $this->root = null;
        $this->scope = null;
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

    public function setContextService(LoggerContextService $contextService): void
    {
        $this->contextService = $contextService;
    }
}
