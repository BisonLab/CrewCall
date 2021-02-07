<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

use App\Lib\ExternalEntityConfig;

class Builder
{
    use \BisonLab\CommonBundle\Menu\StylingTrait;

    private $user = null;
    private $router = null;
    private $factory = null;
    private $container = null;
    private $custom_builder = null;
    private $common_builder = null;
    private $sakonnin_builder = null;

    public function __construct(FactoryInterface $factory, $container)
    {
        $this->factory = $factory;
        $this->container = $container;
        $this->router = $this->container->get('router');
        if ($this->token = $this->container->get('security.token_storage')->getToken())
            $this->user = $this->container->get('security.token_storage')->getToken()->getUser();

        if (class_exists('CustomBundle\Menu\Builder')) {
            $this->custom_builder = new \CustomBundle\Menu\Builder();
        }
        if (class_exists('BisonLab\SakonninBundle\Menu\Builder')) {
            $this->sakonnin_builder = new \BisonLab\SakonninBundle\Menu\Builder();
        }
        if (class_exists('BisonLab\CommonBundle\Menu\Builder')) {
            $this->common_builder = new \BisonLab\CommonBundle\Menu\Builder();
        }
    }

    public function mainMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        if (!$this->user)
            return $menu;

        $menu->addChild('Dashboard', array('route' => 'dashboard'));
        if ($this->user->isAdmin()) {
            $eventsmenu = $menu->addChild('Events', array('route' => 'event_index'));
            $crewmenu = $menu->addChild("Crew",
                array('route' => 'crew_index',
                    'routeParameters' => array('select_grouping' => 'all_active')));
            $menu->addChild('Organizations', array('route' => 'organization_index'));
            $menu->addChild('Locations', array('route' => 'location_index'));

            $adminmenu = $menu->addChild('Admin Stuff', array('route' => ''));
            $adminmenu->addChild('Manage Functions',
                array('route' => 'function_index'));
            $adminmenu->addChild('Manage Roles',
                array('route' => 'role_index'));
            $adminmenu->addChild('Report generator', array('route' => 'reports'));

            $sakonnin = $this->container->get('sakonnin.messages');
            // Do we have a message for the front page?
            $fpnl_type = $sakonnin->getMessageType('Front page not logged in');
;
            if (count($fpnl_type->getMessages()) > 0) {
                $fpm = $fpnl_type->getMessages()[0];
                $elpm = $adminmenu->addChild('Edit login page message', array('uri' => "#"));
                $uri = $this->router->generate('message_edit', array('access' => 'ajax', 'message_id' => $fpm->getId(), 'reload_after_post' => true));
                $elpm->setLinkAttribute('onClick', "return openCcModal('" . $uri . "', 'Edit login page message');");
            } else {
                $alpm = $adminmenu->addChild('Add login page message',
                    array('uri' => '#'));
                $uri = $this->router->generate('message_new', array('access' => 'ajax', 'message_type' => $fpnl_type->getId(), 'reload_after_post' => true));
                $alpm->setLinkAttribute('onClick', "return openCcModal('" . $uri . "', 'Add login page message');");
            }

            $adminmenu->addChild("People with Roles",
                array('route' => 'person_role'));
            $adminmenu->addChild("People with Functions",
                array('route' => 'person_function'));
            if ($this->container->getParameter('allow_registration')) {
                $adminmenu->addChild('Applicants', array('route' => 'person_applicants'));
            }
            // Not sure I need it, reapply in custom if you need it.
            // $adminmenu->addChild('Add person', array('route' => 'person_new'));
            $adminmenu->addChild('Mail and SMS templates',
                array('route' => 'sakonnintemplate_index'));
            $adminmenu->addChild('Message Types',
                array('route' => 'messagetype'));

            $adminmenu->addChild('Mobilefront', array('uri' => '/public/userfront/'));
            $menu->addChild('Jobs view', array('route' => 'jobsview_index'));
        }
        $options['menu']      = $menu;
        $options['container'] = $this->container;
        $options['factory']   = $this->factory;
        $options['router']    = $this->router;
        $options['user']      = $this->user;
        // For local additions to the main menu.
        if ($this->custom_builder
                && method_exists($this->custom_builder, "mainMenu"))
            $menu = $this->custom_builder->mainMenu($this->factory, $options);

        $menu = $this->styleMenuBootstrap($menu, $options);
        return $menu;
    }

    public function userMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu->setLinkAttribute('class', 'fa fa-home');
        //$menu->setExtra('class', 'blatter');
        //$menu->setExtra('class', 'fibble.');
        //$menu->setExtra('span', 'frog.');

        // Yeah, I'd call this a hack too.
        $options['menu']      = $menu;
        $options['factory']   = $this->factory;
        $options['container'] = $this->container;
        $options['router']    = $this->router;
        $options['user']      = $this->user;

        // There must be a user.
        $username = $this->user->getUserName();
        $usermenu = $menu->addChild('<span class="user_glyph"></span>');
        // $usermenu = $menu->addChild($username);
        // $usermenu->addChild('Profile', array('route' => 'user_profile'));
        // $usermenu->addChild('My Jobs', array('route' => 'user_me'));
        // $usermenu->addChild('My Calendar', array('route' => 'user_me_calendar'));
        if ($this->user->isAdmin()) {
            $usermenu->addChild('User view', array('route' => 'user_view'));
            $usermenu->addChild('Admin view', array('route' => 'dashboard'));
            $usermenu->addChild('Change Password', array('route' => 'self_change_password'));
        }

        $profilem = $usermenu->addChild('Profile', array('uri' => '#'));
        $profilem_uri = $this->router->generate('uf_me_profile');
        $profilem->setLinkAttribute('onClick',
            "return getCContent('" . $profilem_uri . "');");

        $usermenu->addChild('Sign Out', array('route' => 'app_logout'));

        if ($this->container->getParameter('enable_personal_messaging')) {
            $usermenu = $this->sakonnin_builder->messageMenu($this->factory, $options);
            if ($this->user->isAdmin()) {
                $pmmenu = $usermenu['Messages']->addChild('Write PM and send SMS', array('uri' => '#'));
                $pmmenu->setLinkAttribute('onclick', 'createPmMessage("PM")');
            } else {
                $usermenu['Messages']->removeChild('Message History');
            }
        }

        // For local customized additions to the main menu.
        if ($this->custom_builder
                && method_exists($this->custom_builder, "userMenu"))
            $menu = $this->custom_builder->userMenu($this->factory, $options);
        $menu = $this->styleMenuBootstrap($menu, $options);
        return $menu;
    }
}
