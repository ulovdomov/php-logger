<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

use Tracy\Debugger;
use Tracy\Logger;
use UlovDomov\Helpers\Dumper;

final class TracyLogger extends Logger
{
    private static LoggerContextService $contextService;

    public function __construct(LoggerContextService $loggerContextService)
    {
        parent::__construct(Debugger::$logDirectory, Debugger::$email, Debugger::getBlueScreen());

        self::$contextService = $loggerContextService;
    }

    public function log(mixed $message, string $level = self::INFO)
    {
        if (\is_object($message) && !$message instanceof \Throwable) {
            $message = $message instanceof \Stringable || \method_exists($message, '__toString')
                ? (string) $message
                : self::dump($message);
        }

        return parent::log($message, $level);
    }

    public static function formatLogLine($message, string|null $exceptionFile = null): string
    {
        $formatted = parent::formatLogLine($message, $exceptionFile);

        $context = self::$contextService->getContext();

        if (\count($context) > 0) {
            $formatted .= ' #  ' . Dumper::toPhp($context);
        }

        $tags = self::$contextService->getTags();
        $tags['processId'] = self::$contextService->getProcessId();

        $ip = self::$contextService->getIpAddress();

        if ($ip !== null) {
            $tags['ip'] = $ip;
        }

        $formatted .= ' ##  ' . Dumper::toPhp($tags);

        if ($message instanceof FingerprintedException) {
            $fingerprint = $message->getFingerprint();

            if ($fingerprint !== null) {
                $formatted .= ' ###  ' . $message->getFingerprint();
            }
        }

        return $formatted;
    }

    private static function dump(mixed $data): string
    {
        return \str_replace("\n", '', Dumper::toPhp($data));
    }
}
