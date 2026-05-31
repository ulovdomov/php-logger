<?php declare(strict_types = 1);

namespace Tests\Package\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use UlovDomov\Logging\OpenTelemetry\Resources\Detectors\SymfonySecurityResourceDetector;

require_once __DIR__ . '/../../../../vendor/autoload.php';

final class SymfonySecurityResourceDetectorTest extends TestCase
{
    public function testCollectsUserMetadata(): void
    {
        $user = new InMemoryUser('john', null, ['ROLE_USER', 'ROLE_ADMIN']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $detector = new SymfonySecurityResourceDetector($tokenStorage);
        $attributes = $detector->getResource()->getAttributes();

        self::assertSame('john', $attributes->get('symfony.security.id'));
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $attributes->get('symfony.security.roles'));
    }

    public function testEmptyResourceWhenNoToken(): void
    {
        $detector = new SymfonySecurityResourceDetector(new TokenStorage());

        self::assertSame([], $detector->getResource()->getAttributes()->toArray());
    }
}
