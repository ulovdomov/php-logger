<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use UlovDomov\Logging\LoggerContextService;

final class MonologContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly LoggerContextService $loggerContextService,
    )
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['processId'] = $this->loggerContextService->getProcessId();

        $ip = $this->loggerContextService->getIpAddress();

        if ($ip !== null) {
            $record->extra['ip'] = $ip;
        }

        if ($this->loggerContextService->environment !== null) {
            $record->extra['environment'] = $this->loggerContextService->environment;
        }

        $tags = $this->loggerContextService->getTags();

        if (\count($tags) > 0) {
            $record->extra['tags'] = $tags;
        }

        $context = $this->loggerContextService->getContext();

        if (\count($context) > 0) {
            $record->extra['context'] = $context;
        }

        return $record;
    }
}
