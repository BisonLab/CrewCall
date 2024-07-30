<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use App\Entity\Person;

/* 
 * This is the way to be able to program "Summaries" on entities. Both
 * here in the main bundle, but also customize whenever needed.
 */
class Dashboarder
{
    private $config;

    public function __construct(
        #[AutowireLocator('app.dashies')] private ServiceLocator $locator,
        private ParameterBagInterface $parameterBag
    ) {
        $this->config = $parameterBag->get('crewcall.dashboarder');
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
            $cust_class = 'CustomBundle\Lib\Dashboarder\\' . $dash['dashie'];
            $dash_class = 'App\Lib\Dashboarder\\' . $dash['dashie'];
            if (in_array($cust_class, $this->locator->getProvidedServices())) {
                $cc = $this->locator->get($cust_class);
                $dash['content'] = $cc->dashize($user);
            } elseif (in_array($dash_class, $this->locator->getProvidedServices())) {
                $cc = $this->locator->get($dash_class);
                $dash['content'] = $cc->dashize($user);
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
