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

        $formatted .= ' #  ' . self::dump(self::$contextService->getTraceInfo());

        $context = self::$contextService->getContext();

        if (\count($context) > 0) {
            $formatted .= ' ##  ' . self::dump($context);
        }

        $userData = self::$contextService->getUserData();

        if (\count($userData) > 0) {
            $formatted .= ' | ' . self::dump($userData);
        }

        $tags = self::$contextService->getTags();

        if (\count($tags) > 0) {
            $formatted .= ' ###  ' . self::dump($tags);
        }

        if ($message instanceof FingerprintedException) {
            $fingerprint = $message->getFingerprint();

            if ($fingerprint !== null) {
                $formatted .= ' ####  ' . $message->getFingerprint();
            }
        }

        return $formatted;
    }

    private static function dump(mixed $data): string
    {
        return \str_replace("\n", '', Dumper::toPhp($data));
    }
}
