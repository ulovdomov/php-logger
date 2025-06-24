<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Metrics\Store;

final class JsonFileMetricStore implements MetricValueStore
{
    private array $data = [];

    public function __construct(private readonly string $file)
    {
        if (\file_exists($file)) {
            $this->data = \json_decode(\file_get_contents($file), true) ?? [];
        }
    }

    public function __destruct()
    {
        $this->collect();
    }

    public function collect(): void
    {
        \file_put_contents($this->file, \json_encode($this->data, \JSON_PRETTY_PRINT));
    }

    public function load(string $metricName, array $labels): float
    {
        $key = $this->key($metricName, $labels);

        return $this->data[$key] ?? 0.0;
    }

    public function save(string $metricName, array $labels, float $newValue): void
    {
        $key = $this->key($metricName, $labels);
        $this->data[$key] = $newValue;
    }

    private function key(string $metricName, array $labels): string
    {
        \ksort($labels);

        return $metricName . ':' . \md5(\json_encode($labels));
    }
}
