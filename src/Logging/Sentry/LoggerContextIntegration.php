<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Sentry;

use Contributte\Sentry\Integration\BaseIntegration;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\HubInterface;
use UlovDomov\Logging\FingerprintedException;
use UlovDomov\Logging\LoggerContextService;

final class LoggerContextIntegration extends BaseIntegration
{
    public function __construct(
        private readonly LoggerContextService $loggerContextService,
    )
    {
    }

    public function setup(HubInterface $hub, Event $event, EventHint $hint): Event|null
    {
        $event->setContext('trace', $this->loggerContextService->getTraceInfo());

        $context = $this->loggerContextService->getContext();

        if (\count($context) > 0) {
            $event->setContext('context', $context);
        }

        if ($this->loggerContextService->environment !== null) {
            $event->setEnvironment($this->loggerContextService->environment);
        }

        $tags = $this->loggerContextService->getTags();

        $ip = $this->loggerContextService->getIpAddress();

        if ($ip !== null) {
            $tags['ip'] = $ip;
        }

        if (\count($tags) > 0) {
            $event->setTags($tags);
        }

        $message = $hint->exception;

        if ($message instanceof FingerprintedException) {
            $fingerprint = $message->getFingerprint();

            if ($fingerprint !== null) {
                $event->setFingerprint([$fingerprint]);
            }
        }

        return $event;
    }
}
