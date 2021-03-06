<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\Organization;
use App\Entity\Event;

/* 
 * This is the way to be able to program "Summaries" on entities. Both
 * here in the main bundle, but also customize whenever needed.
 */
class Dashboarder
{
    private $config;
    private $router;
    private $entitymanager;
    private $twig;

    /*
     * I may need a lot more here. Too bad I can't use the container.
     */
    public function __construct($config, $router, $entitymanager, $twig)
    {
        $this->config = $config;
        $this->router = $router;
        $this->entitymanager = $entitymanager;
        $this->twig = $twig;
    }

    public function dashboards(Person $user)
    {
        $dashes = [];
        foreach ($this->config['roles'] as $role => $elems) {
            if (in_array($role, $user->getRoles())) {
                $dashes += $elems;
                break;
            }
        }
        // And here, go through functions when you have something for them.

        $dashboards = [];
        foreach ($dashes as $dash) {
            $cust_class = '\CustomBundle\Lib\Dashboarder\\' . $dash['dashie'];
            $crew_class = '\App\Lib\Dashboarder\\' . $dash['dashie'];
            if (class_exists($cust_class)) {
                $cc = new $cust_class($this->router,
                    $this->entitymanager,
                    $this->twig);
                $dash['content'] = $cc->dashize($user) ?: "";
            } elseif (class_exists($crew_class)) {
                $cc = new $crew_class($this->router,
                    $this->entitymanager,
                    $this->twig);
                $dash['content'] = $cc->dashize($user) ?: "";
            } else {
                continue;
            }
            /*
             * Cheap and ugly trick.
             */
            if ($dash['cols'] == 0) {
                $last = array_pop($dashboards);
                $last['no_end'] = true;
                $dashboards[] = $last;
                $dash['no_start'] = true;
            }
            $dashboards[] = $dash;
        }
        return $dashboards;
    }
}
