<?php declare(strict_types = 1);

namespace UlovDomov\Logging\OpenTelemetry;

/**
 * @internal
 */
final class Utils
{
    /**
     * @param array<string|string[]> $headers
     *
     * @return array<string>
     *
     * @internal
     */
    public static function anonymizeHeaders(array $headers): array
    {
        $flat = [];

        static $sensitiveHeaders = [
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
        ];

        foreach ($headers as $name => $values) {
            $values = (array) $values;
            $normalized = \strtolower($name);

            $isSensitive = \in_array($normalized, $sensitiveHeaders, true);

            foreach ($values as $value) {
                $line = $isSensitive ? "$name: ***" : "$name: $value";

                if (!\in_array($line, $flat, true)) {
                    $flat[] = $line;
                }
            }
        }

        return $flat;
    }
}
