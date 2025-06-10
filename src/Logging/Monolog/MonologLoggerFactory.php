<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Monolog;

use Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
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

    public function createForMethod(string $method, bool $stdOut = false): LoggerInterface
    {
        $parts = \explode('\\', $method);
        /** @var mixed|false $lastPart */
        $lastPart = \end($parts);

        if ($lastPart !== false) {
            return $this->create(\end($parts), $stdOut);
        }

        throw new \LogicException('Can not create name from: ' . $method);
    }

    public function createStdOut(string $channelName): LoggerInterface
    {
        return $this->create($channelName, true);
    }

    public function create(string $channelName, bool $stdOut = false): LoggerInterface
    {
        $logger = new Logger($channelName);
        $logger->pushProcessor($this->monologContextProcessor);

        if ($stdOut) {
            $handler = new StreamHandler('php://stdout');

            $handler->setFormatter(new JsonFormatter());
            $logger->pushHandler($handler);

            return $logger;
        }

        $fileHandler = new RotatingFileHandler($this->logDir . \DIRECTORY_SEPARATOR . $channelName . '.log');

        if (\class_exists(ElasticCommonSchemaFormatter::class)) {
            try {
                $fileHandler->setFormatter(new ElasticCommonSchemaFormatter());
            } catch (\RuntimeException $e) {
                throw LogicException::createFromPrevious($e);
            }
        }

        $logger->pushHandler($fileHandler);

        return $logger;
    }
}
