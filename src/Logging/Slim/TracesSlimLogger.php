<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Slim;

use OpenTelemetry\API\Trace\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use UlovDomov\Exceptions\LogicException;
use UlovDomov\Logging\OpenTelemetry\Traces\Span;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;
use UlovDomov\Logging\OpenTelemetry\Utils;
use UlovDomov\Slim\Logging\SlimLogger;

final class TracesSlimLogger implements SlimLogger
{
    private Span|null $requestSpan = null;

    public function __construct(private readonly Tracer $tracer)
    {
    }

    public function catchRequest(ServerRequestInterface $request): void
    {
        if (!$this->tracer->isEnabled()) {
            $this->tracer->enable();
        }

        $query = $request->getUri()->getQuery();

        $span = $this->tracer->startSpan('Slim.request', [
            'endpoint' => $request->getUri()->withQuery('')->__toString(),
            'http_method' => $request->getMethod(),
            'http_query' => $query,
            'headers' => Utils::anonymizeHeaders($request->getHeaders()),
            'body' => $this->getBodyContent($request->getBody()),
        ]);
        $this->requestSpan = $span;
    }

    public function catchResponse(ResponseInterface $response): void
    {
        if ($this->requestSpan === null) {
            throw LogicException::create('Method catchRequest must be called first.');
        }

        $this->tracer->startSpan('Slim.response', [
            'http_status' => $response->getStatusCode(),
            'headers' => Utils::anonymizeHeaders($response->getHeaders()),
            'body' => $this->getBodyContent($response->getBody()),
        ])->end();

        $this->requestSpan->end();

        $this->requestSpan = null;

        if ($this->tracer->isEnabled()) {
            $this->tracer->end();
        }
    }

    public function catchThrowable(\Throwable $throwable): void
    {
        if ($this->requestSpan === null) {
            throw LogicException::create('Method catchRequest must be called first.');
        }

        $this->requestSpan->recordException($throwable);
        $this->requestSpan->setStatus(StatusCode::STATUS_ERROR);
    }

    private function getBodyContent(StreamInterface $stream): string|null
    {
        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $maxLength = 20000;
            $result = '';
            $truncated = false;

            while (!$stream->eof()) {
                $chunk = $stream->read(1024);

                if ($chunk === '') {
                    break;
                }

                $remaining = $maxLength - \mb_strlen($result, '8bit');

                if ($remaining <= 0) {
                    $truncated = true;

                    break;
                }

                $result .= \mb_substr($chunk, 0, $remaining, '8bit');

                if (\mb_strlen($chunk, '8bit') > $remaining) {
                    $truncated = true;

                    break;
                }
            }

            if ($result === '') {
                return null;
            }

            if (!\mb_check_encoding($result, 'UTF-8')) {
                return 'binary';
            }

            return $truncated ? $result . '…' : $result;
        } catch (\Throwable $e) {
            return 'Can not load stream: ' . $e->getMessage();
        }
    }
}
