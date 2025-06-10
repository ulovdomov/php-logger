<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

use Ramsey\Uuid\Uuid;
use UlovDomov\Exceptions\LogicException;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;

final class LoggerContextService
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
        private readonly Tracer|null $tracer = null,
    )
    {
        if ($tracer !== null) {
            $tracer->setContextService($this);
        }
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
                $candidate = \trim($candidate);

                if (\filter_var(
                    $candidate,
                    \FILTER_VALIDATE_IP,
                    \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
                ) !== false) {
                    return $candidate;
                }
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

    /**
     * @return array<string|int>
     */
    public function getUserData(): array
    {
        $result = [];
        $ip = $this->getIpAddress();

        if ($ip !== null) {
            $result['ip_address'] = $ip;
        }

        if ($this->userId !== null) {
            $result['id'] = $this->userId;
        }

        if ($this->userEmail !== null) {
            $result['email'] = $this->userEmail;
        }

        if ($this->username !== null) {
            $result['username'] = $this->username;
        }

        return $result;
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

    /**
     * @deprecated use {@link getTraceId()}
     */
    public function getProcessId(): string
    {
        return $this->getTraceId();
    }

    public function getTraceId(): string
    {
        if ($this->tracer !== null) {
            return $this->tracer->getCurrent()->getContext()->getTraceId();
        }

        if ($this->processId === null) {
            $this->processId = Uuid::uuid4()->toString();
        }

        return $this->processId;
    }

    public function getSpanId(): string
    {
        if ($this->tracer !== null) {
            return $this->tracer->getCurrent()->getContext()->getSpanId();
        }

        if ($this->spanId === null) {
            try {
                $this->spanId = \bin2hex(\random_bytes(8));
            } catch (\Throwable) {
                $this->spanId = self::createSpanId(\uniqid('', true));
            }
        }

        return $this->spanId;
    }

    /**
     * @return array<string>
     */
    public function getTraceInfo(): array
    {
        return [
            'trace_id' => \str_replace('-', '', $this->getTraceId()),
            'span_id' => $this->getSpanId(),
            'status' => 'default',
        ];
    }

    public function getTracer(): Tracer
    {
        if ($this->tracer === null) {
            throw LogicException::create('Open Telemetry client is not configured.');
        }

        return $this->tracer;
    }

    private static function createSpanId(string $identifier): string
    {
        $spanId = \bin2hex(\hash('sha256', $identifier, true));

        return \substr($spanId, 0, 16);
    }
}
