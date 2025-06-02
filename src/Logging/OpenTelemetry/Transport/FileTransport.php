<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Transport;

use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;

/**
 * @implements TransportInterface<'application/json'>
 */
final class FileTransport implements TransportInterface
{
    private string $path;

    public function __construct(string|null $directory = null)
    {
        $directory = $directory . \DIRECTORY_SEPARATOR . 'open-telemetry';

        @\mkdir($directory);

        $this->path = \rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . 'transport.log';
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * @return FutureInterface<bool>
     */
    public function send(string $payload, CancellationInterface|null $cancellation = null): FutureInterface
    {
        \file_put_contents($this->path, $payload . \PHP_EOL, \FILE_APPEND | \LOCK_EX);

        return new CompletedFuture(true);
    }

    public function shutdown(CancellationInterface|null $cancellation = null): bool
    {
        return true;
    }

    public function forceFlush(CancellationInterface|null $cancellation = null): bool
    {
        return true;
    }
}
