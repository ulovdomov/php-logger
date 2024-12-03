<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

use Ramsey\Uuid\Uuid;

final class LoggerContextService
{
    private string|null $processId = null;

    private string|null $spanId = null;

    private string $spanStatus = 'unknown';

    /**
     * @var array<mixed>
     */
    private array $context = [];

    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        public readonly string|null $environment = null,
        public array $tags = [],
    )
    {
    }

    public function getIpAddress(): string|null
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function addTag(string $name, string|int|float $value): void
    {
        $this->tags[$name] = (string) $value;
    }

    /**
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function addContext(string|int $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<mixed> $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function getProcessId(): string
    {
        if ($this->processId === null) {
            $this->processId = Uuid::uuid4()->toString();
        }

        return $this->processId;
    }

    public function getSpanId(): string
    {
        if ($this->spanId === null) {
            try {
                $this->spanId = \bin2hex(\random_bytes(8));
            } catch (\Throwable) {
                $this->spanId = self::createSpanId(\uniqid('', true));
            }
        }

        return $this->spanId;
    }

    public function setSpan(string|int $identifier, string|null $status = null): void
    {
        $this->spanId = self::createSpanId((string) $identifier);

        if ($status !== null) {
            $this->spanStatus = $status;
        }
    }

    /**
     * @return array<string>
     */
    public function getTraceInfo(): array
    {
        return [
            'trace_id' => \str_replace('-', '', $this->getProcessId()),
            'span_id' => $this->getSpanId(),
            'status' => $this->spanStatus,
        ];
    }

    private static function createSpanId(string $identifier): string
    {
        $spanId = \bin2hex(\hash('sha256', $identifier, true));

        return \substr($spanId, 0, 16);
    }
}
