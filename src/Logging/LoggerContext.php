<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

use Ramsey\Uuid\Uuid;

final class LoggerContext
{
    private string|null $processId = null;

    private string|null $spanId = null;

    private string|int|null $userId = null;

    private string|null $userEmail = null;

    private string|null $username = null;

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
        $sources = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_CLIENT_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($sources as $ip) {
            if (!$ip) {
                continue;
            }

            $ipList = \explode(',', $ip);
            foreach ($ipList as $candidate) {
                return \trim($candidate);
            }
        }

        return null;
    }

    public function setUser(string|int $id, string|null $email = null, string|null $username = null): void
    {
        $this->userId = $id;
        $this->userEmail = $email;
        $this->username = $username;
    }

    public function getUserEmail(): string|null
    {
        return $this->userEmail;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    public function getUsername(): string|null
    {
        return $this->username;
    }

    public function addTag(string $name, string|int|float $value): void
    {
        $this->tags[$name] = (string) $value;
    }

    public function removeTag(string $name): void
    {
        unset($this->tags[$name]);
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

    public function getTraceId(): string
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

    private static function createSpanId(string $identifier): string
    {
        $spanId = \bin2hex(\hash('sha256', $identifier, true));

        return \substr($spanId, 0, 16);
    }
}
