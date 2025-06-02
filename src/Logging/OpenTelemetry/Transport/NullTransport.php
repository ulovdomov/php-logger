<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Transport;

use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;

/**
 * @implements TransportInterface<'application/json'>
 */
final class NullTransport implements TransportInterface
{
    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * @return FutureInterface<bool>
     */
    public function send(string $payload, CancellationInterface|null $cancellation = null): FutureInterface
    {
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
