<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Monolog;

use Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use UlovDomov\Exceptions\LogicException;

final class MonologLoggerFactory
{
    public function __construct(
        private readonly MonologContextProcessor $monologContextProcessor,
        private readonly string $logDir,
    ) {
    }

    public function createForMethod(string $method): LoggerInterface
    {
        $parts = \explode('\\', $method);
        /** @var mixed|false $lastPart */
        $lastPart = \end($parts);

        if ($lastPart !== false) {
            return $this->create(\end($parts));
        }

        throw new \LogicException('Can not create name from: ' . $method);
    }

    public function create(string $channelName): LoggerInterface
    {
        $logger = new Logger($channelName);
        $logger->pushProcessor($this->monologContextProcessor);

        $handler = new RotatingFileHandler($this->logDir . \DIRECTORY_SEPARATOR . $channelName . '.log');

        if (\class_exists(ElasticCommonSchemaFormatter::class)) {
            try {
                $handler->setFormatter(new ElasticCommonSchemaFormatter());
            } catch (\RuntimeException $e) {
                throw LogicException::createFromPrevious($e);
            }
        }

        $logger->pushHandler($handler);

        return $logger;
    }
}
