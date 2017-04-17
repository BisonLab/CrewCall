<?php

namespace CustomBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Builder implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function mainMenu(FactoryInterface $factory, array $options)
    {
        // Seems like the container is not injected here. I am probably calling
        // this builder the wrong way.
        $menu = $options['menu'];
        $container = $options['container'];

        if ($container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            // This is not really for the base. But will fix later.
            $menu->addChild("Migration");

            $router = $options['container']->get('router');

            foreach (\MigBundle\Controller\MigrationController::getMigTables() as $t => $d) {
                $route = $router->generate('migration_list', array('table' => $t));
                $menu["Migration"]->addChild($d['model'], array('uri' => $route));
            }
        }
        return $menu;
    }
}
