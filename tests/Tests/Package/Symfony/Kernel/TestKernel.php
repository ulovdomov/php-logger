<?php declare(strict_types = 1);

namespace Tests\Package\Symfony\Kernel;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use UlovDomov\Logging\Symfony\Bundle\UlovDomovLoggingBundle;

final class TestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $loggingConfig
     */
    public function __construct(private readonly array $loggingConfig)
    {
        parent::__construct('test', false);
    }

    /**
     * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new UlovDomovLoggingBundle(),
        ];
    }

    /**
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
            ]);
            $container->loadFromExtension('ulov_domov_logging', $this->loggingConfig);
        });
    }

    public function getCacheDir(): string
    {
        return \sys_get_temp_dir() . '/ud-logging-bundle-test/cache/' . \spl_object_id($this);
    }

    public function getLogDir(): string
    {
        return \sys_get_temp_dir() . '/ud-logging-bundle-test/log';
    }
}
