<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Metrics;

use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use UlovDomov\Logging\OpenTelemetry\Metrics\Store\MetricValueStore;
use UlovDomov\Logging\OpenTelemetry\OpenTelemetryClient;
use UlovDomov\Logging\OpenTelemetry\Resources\ResourceDetector;

final class Meter
{
    private MetricReaderInterface|null $reader = null;
    private MeterProviderInterface|null $metterProvider = null;

    public function __construct(
        private readonly string $name,
        private readonly string $prefix,
        private readonly OpenTelemetryClient $openTelemetryClient,
        private readonly ResourceDetector $resourceDetector,
        private MetricValueStore|null $store = null,
    )
    {

    }

    public function __destruct()
    {
        if ($this->isEnabled()) {
            $this->end();
        }
    }

    public function isEnabled(): bool
    {
        return $this->reader !== null;
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function addGauge(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getMetterProvider()
            ->getMeter($this->name)
            ->createGauge($this->prefix($name), $unit, $description)
            ->record($amount, $attributes);
    }

    /**
     * @param callable(ObserverInterface): void $callback
     */
    public function addObservableGauge(
        string $name,
        callable $callback,
        string|null $unit = null,
        string|null $description = null,
    ): ObservableCallbackInterface
    {
        return $this->getMetterProvider()
            ->getMeter($this->name)
            ->createObservableGauge($this->prefix($name), $unit, $description)
            ->observe($callback);
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function addCounter(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $key = $this->prefix($name);
        $attributesArray = \is_array($attributes) ? $attributes : \iterator_to_array($attributes);

        if ($this->store !== null) {
            $previous = $this->store->load($key, $attributesArray);
            $new = $previous + $amount;
            $this->store->save($key, $attributesArray, $new);
            $amount = $new;
        }

        $this->getMetterProvider()
            ->getMeter($this->name)
            ->createCounter($key, $unit, $description)
            ->add($amount, $attributesArray);
    }

    /**
     * @param callable(ObserverInterface): void $callback
     */
    public function addObservableCounter(
        string $name,
        callable $callback,
        string|null $unit = null,
        string|null $description = null,
    ): ObservableCallbackInterface
    {
        return $this->getMetterProvider()
            ->getMeter($this->name)
            ->createObservableCounter($this->prefix($name), $unit, $description)
            ->observe($callback);
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function addHistogram(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getMetterProvider()
            ->getMeter($this->name)
            ->createHistogram($this->prefix($name), $unit, $description)
            ->record($amount, $attributes);
    }

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $attributes
     */
    public function addUpDownCounter(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getMetterProvider()
            ->getMeter($this->name)
            ->createUpDownCounter($this->prefix($name), $unit, $description)
            ->add($amount, $attributes);
    }

    /**
     * @param callable(ObserverInterface): void $callback
     */
    public function addObservableUpDownCounter(
        string $name,
        callable $callback,
        string|null $unit = null,
        string|null $description = null,
    ): ObservableCallbackInterface
    {
        return $this->getMetterProvider()
            ->getMeter($this->name)
            ->createObservableUpDownCounter($this->prefix($name), $unit, $description)
            ->observe($callback);
    }

    public function end(): void
    {
        if (!$this->isEnabled()) {
            throw new \LogicException('Metrics are not enabled. First create some observables.');
        }

        $this->collect();
        $this->getReader()->shutdown();

        $this->getMetterProvider()->shutdown();

        $this->reader = null;
        $this->metterProvider = null;
    }

    public function collect(): void
    {
        if (!$this->isEnabled()) {
            throw new \LogicException('Metrics are not enabled.  First create some observables.');
        }

        $this->getReader()->collect();

        if ($this->store !== null) {
            $this->store->collect();
        }
    }

    private function prefix(string $name): string
    {
        return $this->prefix . '_' . $name;
    }

    private function getReader(): MetricReaderInterface
    {
        if ($this->reader === null) {
            $this->reader = new ExportingReader(
                new MetricExporter($this->openTelemetryClient->getMetricsTransport()),
            );
        }

        return $this->reader;
    }

    private function getMetterProvider(): MeterProviderInterface
    {
        if ($this->metterProvider === null) {
            $this->metterProvider = MeterProvider::builder()
                ->setResource($this->resourceDetector->getResource($this->name))
                ->addReader($this->getReader())
                ->build();
        }

        return $this->metterProvider;
    }
}
