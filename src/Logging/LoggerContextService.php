<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

use Ramsey\Uuid\Uuid;

final class LoggerContextService
{
    private string|null $processId = null;

    /**
     * @var array<string, string>
     */
    private array $tags = [];

    /**
     * @var array<mixed>
     */
    private array $context = [];

    public function __construct(public readonly string|null $environment = null)
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
}
