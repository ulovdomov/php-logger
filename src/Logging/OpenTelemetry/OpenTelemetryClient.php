<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry;

use OpenTelemetry\API\Signals;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use UlovDomov\Exceptions\LogicException;
use UlovDomov\Logging\OpenTelemetry\Transport\FileTransport;
use UlovDomov\Logging\OpenTelemetry\Transport\NullTransport;

final class OpenTelemetryClient
{
    /**
     * @var TransportInterface<ContentTypes::PROTOBUF|ContentTypes::JSON>|null
     */
    private TransportInterface|null $traceTransport;

    /**
     * @var TransportInterface<ContentTypes::PROTOBUF|ContentTypes::JSON>|null
     */
    private TransportInterface|null $metricsTransport;

    public function __construct(
        string|null $url,
        TransportType $type,
        string|null $metricsUrl,
        TransportType $metricsType,
    )
    {
        $this->traceTransport = $url !== null && \strlen(\trim($url)) > 0 ? $this->createTransport(
            $url,
            $type,
            Signals::TRACE,
            'traces',
        ) : null;

        $this->metricsTransport = $metricsUrl !== null && \strlen(\trim($metricsUrl)) > 0 ? $this->createTransport(
            $metricsUrl,
            $metricsType,
            Signals::METRICS,
            'metrics',
        ) : null;
    }

    /**
     * @return TransportInterface<ContentTypes::PROTOBUF|ContentTypes::JSON>
     */
    private function createTransport(
        string $url,
        TransportType $type,
        string $signal,
        string $fileName,
    ): TransportInterface
    {
        $endpoint = \rtrim($url, '/') . OtlpUtil::path($signal, $type->getProtocol());

        return match ($type) {
            TransportType::File => new FileTransport($fileName, $url),
            TransportType::Grpc => (new GrpcTransportFactory())->create($endpoint),
            TransportType::Http => (new OtlpHttpTransportFactory())->create($endpoint, ContentTypes::JSON),
            TransportType::HttpProtobuf => (new OtlpHttpTransportFactory())->create(
                $endpoint,
                ContentTypes::PROTOBUF,
            ),
            TransportType::Null => new NullTransport(),
        };
    }

    /**
     * @return TransportInterface<ContentTypes::PROTOBUF|ContentTypes::JSON>
     */
    public function getTraceTransport(): TransportInterface
    {
        if ($this->traceTransport === null) {
            throw LogicException::create('Trace transport is not configured');
        }

        return $this->traceTransport;
    }

    /**
     * @return TransportInterface<ContentTypes::PROTOBUF|ContentTypes::JSON>
     */
    public function getMetricsTransport(): TransportInterface
    {
        if ($this->metricsTransport === null) {
            throw LogicException::create('Trace transport is not configured');
        }

        return $this->metricsTransport;
    }
}
