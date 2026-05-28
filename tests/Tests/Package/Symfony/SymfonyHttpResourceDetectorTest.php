<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonyHttpResourceDetector;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class SymfonyHttpResourceDetectorTest extends TestCase
{
    public function testCollectsRequestMetadata(): void
    {
        $request = Request::create('http://example.com/orders/42?page=2', 'POST');
        $request->attributes->set('_route', 'order_detail');

        $stack = new RequestStack();
        $stack->push($request);

        $detector = new SymfonyHttpResourceDetector($stack);
        $attributes = $detector->getResource()->getAttributes();

        self::assertSame('POST', $attributes->get('http.request.method'));
        self::assertSame('page=2', $attributes->get('http.request.query_string'));
        self::assertSame('http://example.com/orders/42', $attributes->get('http.request.url'));
        self::assertSame('order_detail', $attributes->get('http.route'));
    }

    public function testEmptyResourceWhenNoRequest(): void
    {
        $detector = new SymfonyHttpResourceDetector(new RequestStack());

        self::assertSame([], $detector->getResource()->getAttributes()->toArray());
    }
}
