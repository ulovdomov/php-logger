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

class TracesSlimLogger implements SlimLogger
{
    private Span|null $requestSpan = null;

    private ServerRequestInterface|null $lastRequest = null;

    /**
     * @var array<string>
     */
    private array $requestData = [];

    public function __construct(private readonly Tracer $tracer)
    {
    }

    protected function getCurrentRequest(): ServerRequestInterface|null
    {
        return $this->lastRequest;
    }

    public function catchRequest(ServerRequestInterface $request): void
    {
        $this->lastRequest = $request;

        if (!$this->tracer->isEnabled()) {
            $this->tracer->enable();
        }

        $this->requestData = [
            'endpoint' => $request->getUri()->withQuery('')->__toString(),
            'http_method' => $request->getMethod(),
        ];

        $query = $request->getUri()->getQuery();

        $bodyStream = $request->getBody();
        $body = $this->shouldReturnOnlySize($bodyStream) ? $this->getBodySize($bodyStream) : $this->getBodyContent(
            $bodyStream,
        );

        $span = $this->tracer->startSpan('Slim.request', $this->requestData + [
                'http_query' => $query,
                'headers' => Utils::anonymizeHeaders($request->getHeaders()),
                'body' => $body,
            ]);
        $this->requestSpan = $span;
    }

    public function catchResponse(ResponseInterface $response): void
    {
        if ($this->requestSpan === null) {
            throw LogicException::create('Method catchRequest must be called first.');
        }

        $bodyStream = $response->getBody();
        $body = $this->shouldReturnOnlySize($bodyStream) ? $this->getBodySize($bodyStream) : $this->getBodyContent(
            $bodyStream,
        );

        $this->tracer->startSpan('Slim.response', $this->requestData + [
                'http_status' => $response->getStatusCode(),
                'headers' => Utils::anonymizeHeaders($response->getHeaders()),
                'body' => $body,
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

    protected function shouldReturnOnlySize(StreamInterface $stream): bool
    {
        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $preview = $stream->read(4096);

            // Reset pointer for subsequent operations
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            if ($preview === '') {
                return false; // empty body -> treat as text (we will return null later)
            }

            return !\mb_check_encoding($preview, 'UTF-8');
        } catch (\Throwable) {
            // In case of any error determining, be safe and do not treat as binary
            return false;
        }
    }

    /**
     * Return the size of the body in bytes, if determinable.
     */
    protected function getBodySize(StreamInterface $stream): int|null
    {
        try {
            // Try native size first
            $size = $stream->getSize();

            if ($size !== null) {
                return $size;
            }

            // Fall back to counting bytes by reading
            $total = 0;

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (!$stream->eof()) {
                $chunk = $stream->read(8192);

                if ($chunk === '') {
                    break;
                }

                $total += \mb_strlen($chunk, '8bit');
            }

            // Reset pointer for other consumers
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            return $total;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getBodyContent(StreamInterface $stream): string|null
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
