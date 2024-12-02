<?php declare(strict_types = 1);

namespace UlovDomov\Logging;

interface FingerprintedException extends \Throwable
{
    public function getFingerprint(): string|null;
}
