<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use App\Lib\ExternalEntityConfig;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';
        $custombundlesfile = $this->getProjectDir().'/src/CustomBundle/config/bundles.php';
        if (file_exists($custombundlesfile)) {
            $c_contents = require $custombundlesfile;
            $contents = array_merge($contents, $c_contents);
        }
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');
        } elseif (is_file($path = \dirname(__DIR__).'/config/services.php')) {
            (require $path)($container->withPath($path), $this);
        }

        if (in_array('CustomBundle', array_keys($this->getBundles()))) {
            $container->import('@CustomBundle/config/services.yaml');
            $container->import('@CustomBundle/config/{packages}/*.yaml');
            $container->import('@CustomBundle/config/'.$this->environment.'/*.yaml', null, true);
        }
    }

    public function boot()
    {
        parent::boot();
        ExternalEntityConfig::setStatesConfig($this->getContainer()->getParameter('app.states')["App"]);
        ExternalEntityConfig::setTypesConfig($this->getContainer()->getParameter('app.types')["App"]);
        ExternalEntityConfig::setSystemRoles($this->getContainer()->getParameter('crewcall.system_roles'));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/{routes}/'.$this->environment.'/*.yaml');
        $routes->import('../config/{routes}/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/routes.yaml')) {
            $routes->import('../config/routes.yaml');
        } elseif (is_file($path = \dirname(__DIR__).'/config/routes.php')) {
            (require $path)($routes->withPath($path), $this);
        }
        if (in_array('CustomBundle', array_keys($this->getBundles()))) {
            $routes->import('@CustomBundle/config/routes.yaml');
            $routes->import('@CustomBundle/config/routes/'.$this->environment.'.yaml', null, true);
        }
    }
}
