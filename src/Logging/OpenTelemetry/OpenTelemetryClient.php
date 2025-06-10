<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry;

use OpenTelemetry\API\Signals;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use UlovDomov\Logging\OpenTelemetry\Transport\FileTransport;
use UlovDomov\Logging\OpenTelemetry\Transport\NullTransport;

final class OpenTelemetryClient
{
    /**
     * @var TransportInterface<ContentTypes::PROTOBUF|ContentTypes::JSON>
     */
    private TransportInterface $transport;

    public function __construct(string $url, TransportType $type)
    {
        $endpoint = \rtrim($url, '/') . OtlpUtil::method(Signals::TRACE);

        $this->transport = match ($type) {
            TransportType::File => new FileTransport($url),
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
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }
}
