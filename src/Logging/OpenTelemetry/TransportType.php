<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry;

enum TransportType: string
{
    case File = 'file';
    case Grpc = 'grpc';
    case Http = 'http';
    case HttpProtobuf = 'http-protobuf';
    case Null = 'null';

    public function getProtocol(): string
    {
        if ($this === self::Grpc) {
            return 'grpc';
        }
        return 'http';
    }
}
