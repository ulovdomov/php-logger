<?php declare(strict_types = 1);

namespace Tests\Libraries;

use Nette\Bootstrap\Configurator;

final class TestBootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator();
        $configurator->enableTracy(__DIR__ . '/../../log');
        $configurator->setDebugMode(false);

        $configurator->setTimeZone('Europe/Prague');
        $configurator->setTempDirectory(__DIR__ . '/../../temp');
        $configurator->addConfig(__DIR__ . '/../config/common.neon');

        $configurator->addStaticParameters([
            'rootDir' => __DIR__ . '/../..',
            'logDir' => __DIR__ . '/../../log',
        ]);

        return $configurator;
    }
}
