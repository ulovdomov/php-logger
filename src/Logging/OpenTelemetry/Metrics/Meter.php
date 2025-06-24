<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Metrics;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObservableCounterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
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

    /**
     * @var array<string, object>
     */
    private array $registeredInstruments = [];

    public function __construct(
        private readonly string $name,
        private readonly string $prefix,
        private readonly OpenTelemetryClient $openTelemetryClient,
        private readonly ResourceDetector $resourceDetector,
        private MetricValueStore|null $store = null,
    ) {
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

    public function addGauge(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getGauge($name, $unit, $description)->record($amount, $attributes);
    }

    private function getGauge(
        string $name,
        string|null $unit,
        string|null $description,
    ): \OpenTelemetry\API\Metrics\GaugeInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            \OpenTelemetry\API\Metrics\GaugeInterface::class,
            function () use ($key, $unit, $description) {
                return $this->getMetterProvider()->getMeter($this->name)->createGauge($key, $unit, $description);
            },
        );
    }

    public function addObservableGauge(
        string $name,
        callable $callback,
        string|null $unit = null,
        string|null $description = null,
    ): ObservableCallbackInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            ObservableGaugeInterface::class,
            function () use ($key, $unit, $description, $callback) {
                return $this->getMetterProvider()->getMeter($this->name)->createObservableGauge(
                    $key,
                    $unit,
                    $description,
                )->observe(
                    $callback,
                );
            },
        );
    }

    public function addCounter(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getCounter($name, $unit, $description)->add(
            $this->cacheValue($this->prefix($name), $amount, $attributes),
            $attributes,
        );
    }

    private function getCounter(string $name, string|null $unit, string|null $description): CounterInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            CounterInterface::class,
            function () use ($key, $unit, $description) {
                return $this->getMetterProvider()->getMeter($this->name)->createCounter($key, $unit, $description);
            },
        );
    }

    public function addObservableCounter(
        string $name,
        callable $callback,
        string|null $unit = null,
        string|null $description = null,
    ): ObservableCallbackInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            ObservableCounterInterface::class,
            function () use ($key, $unit, $description, $callback) {
                return $this->getMetterProvider()->getMeter($this->name)->createObservableCounter(
                    $key,
                    $unit,
                    $description,
                )->observe(function (ObserverInterface $observer) use ($key, $callback): void {
                    $callback(new class($key, $observer, $this->store) implements ObserverInterface {
                        public function __construct(
                            private readonly string $key,
                            private readonly ObserverInterface $observer,
                            private readonly MetricValueStore $store,
                        ) {
                        }

                        public function observe(float|int $amount, iterable $attributes = []): void
                        {
                            $value = $this->store->load($this->key, $attributes);
                            $this->observer->observe($value, $attributes);
                        }
                    });
                });
            },
        );
    }

    public function addHistogram(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getHistogram($name, $unit, $description)->record(
            $this->cacheValue($this->prefix($name), $amount, $attributes),
            $attributes,
        );
    }

    private function getHistogram(string $name, string|null $unit, string|null $description): HistogramInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            HistogramInterface::class,
            function () use ($key, $unit, $description) {
                return $this->getMetterProvider()->getMeter($this->name)->createHistogram($key, $unit, $description);
            },
        );
    }

    public function addUpDownCounter(
        string $name,
        float|int $amount,
        string|null $unit = null,
        string|null $description = null,
        iterable $attributes = [],
    ): void
    {
        $this->getUpDownCounter($name, $unit, $description)->add(
            $this->cacheValue($this->prefix($name), $amount, $attributes),
            $attributes,
        );
    }

    private function getUpDownCounter(string $name, string|null $unit, string|null $description): UpDownCounterInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            UpDownCounterInterface::class,
            function () use ($key, $unit, $description) {
                return $this->getMetterProvider()->getMeter($this->name)->createUpDownCounter(
                    $key,
                    $unit,
                    $description,
                );
            },
        );
    }

    public function addObservableUpDownCounter(
        string $name,
        callable $callback,
        string|null $unit = null,
        string|null $description = null,
    ): ObservableCallbackInterface
    {
        $key = $this->prefix($name);

        return $this->getOrRegisterInstrument(
            $key,
            ObservableUpDownCounterInterface::class,
            function () use ($key, $unit, $description, $callback) {
                return $this->getMetterProvider()->getMeter($this->name)->createObservableUpDownCounter(
                    $key,
                    $unit,
                    $description,
                )->observe(
                    function (ObserverInterface $observer) use ($key, $callback): void {
                        $callback(new class($key, $observer, $this->store) implements ObserverInterface {
                            public function __construct(
                                private readonly string $key,
                                private readonly ObserverInterface $observer,
                                private readonly MetricValueStore $store,
                            ) {
                            }

                            public function observe(float|int $amount, iterable $attributes = []): void
                            {
                                $value = $this->store->load($this->key, $attributes);
                                $this->observer->observe($value, $attributes);
                            }
                        });
                    },
                );
            },
        );
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
            throw new \LogicException('Metrics are not enabled. First create some observables.');
        }

        $this->getReader()->collect();
        $this->store?->collect();
    }

    private function prefix(string $name): string
    {
        return $this->prefix . '_' . $name;
    }

    private function getReader(): MetricReaderInterface
    {
        return $this->reader ??= new ExportingReader(
            new MetricExporter($this->openTelemetryClient->getMetricsTransport()),
        );
    }

    private function getMetterProvider(): MeterProviderInterface
    {
        return $this->metterProvider ??= MeterProvider::builder()
            ->setResource($this->resourceDetector->getResource($this->name))
            ->addReader($this->getReader())
            ->build();
    }

    private function cacheValue(string $key, float|int $amount, array $attributes = []): float|int
    {
        if ($this->store === null) {
            return $amount;
        }

        $attributesArray = \is_array($attributes) ? $attributes : \iterator_to_array($attributes);
        $previous = $this->store->load($key, $attributesArray);
        $new = $previous + $amount;
        $this->store->save($key, $attributesArray, $new);

        return $new;
    }

    /**
     * @template T
     *
     * @param class-string<T> $type
     * @param \Closure(): void $factory
     *
     * @return object<T>
     */
    private function getOrRegisterInstrument(string $key, string $type, \Closure $factory): object
    {
        if (isset($this->registeredInstruments[$key])) {
            $existing = $this->registeredInstruments[$key];

            if (!$existing instanceof $type) {
                throw new \LogicException(\sprintf(
                    'Instrument "%s" already registered with a different type (%s vs %s)',
                    $key,
                    $existing::class,
                    $type,
                ));
            }

            return $existing;
        }

        return $this->registeredInstruments[$key] = $factory();
    }
}
