<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry\Resources\Detectors;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tracy\ILogger;

final class SlimHttpResourceDetector implements ResourceDetectorInterface, MiddlewareInterface
{
    private ServerRequestInterface|null $request = null;

    public function __construct(private readonly ILogger $logger)
    {

    }

    public function getResource(): ResourceInfo
    {
        $attributes = [];

        // There is no http request
        if ($this->request === null) {
            return ResourceInfo::emptyResource();
        }

        $attributes['http.request.url'] = $this->request->getUri()->__toString();
        $attributes['http.request.method'] = $this->request->getMethod();

        if ($this->request->getUri()->getQuery() !== '') {
            $attributes['http.request.query_string'] = $this->request->getUri()->getQuery();
        }

        $attributes['http.request.cookies'] = $this->request->getCookieParams();
        $attributes['http.request.headers'] = $this->request->getHeaders();

        $body = $this->request->getBody();

        try {
            if ($body->getSize() > 0) {
                $body->rewind();
                $attributes['http.request.data'] = $body->getContents();
            }
        } catch (\Throwable $e) {
            $this->logger->log($e, ILogger::WARNING);
        } finally {
            $body->close();
        }

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request = $request;

        return $handler->handle($request);
    }
}
