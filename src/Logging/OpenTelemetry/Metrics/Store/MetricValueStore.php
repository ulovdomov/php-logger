<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Metrics\Store;

interface MetricValueStore
{
    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $labels
     */
    public function load(string $metricName, array $labels): float;

    /**
     * @param iterable<non-empty-string, array<mixed>|bool|float|int|string|null> $labels
     */
    public function save(string $metricName, array $labels, float $newValue): void;

    public function collect(): void;
}
