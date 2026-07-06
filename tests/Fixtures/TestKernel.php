<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Fixtures;

use ControleOnline\SmokeTestsPlayground\SmokeTestsPlaygroundBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SmokeTestsPlaygroundBundle();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'test' => true,
            'csrf_protection' => false,
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(dirname(__DIR__, 2).'/src/Controller/', 'attribute');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/smoke-tests-playground/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/smoke-tests-playground/log';
    }
}
