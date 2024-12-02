<?php declare(strict_types = 1);

namespace Tests\Package;

use PHPUnit\Framework\TestCase;
use UlovDomov\Logging\LoggerContextService;

require_once __DIR__ . '/../../../vendor/autoload.php';

final class BasicTest extends TestCase
{
    public function testBasic(): void
    {
        $contextService = new LoggerContextService('test');
        self::assertSame('test', $contextService->environment);

        self::assertIsString($contextService->getProcessId());

        $contextService->addTag('test', 123);
        $tags = $contextService->getTags();
        self::assertSame(['test' => '123'], $tags);

        $contextService->setContext(['key' => 'value']);
        self::assertSame(['key' => 'value'], $contextService->getContext());

        $contextService->addContext('foo', [123]);
        self::assertSame(['key' => 'value', 'foo' => [123]], $contextService->getContext());
    }
}
