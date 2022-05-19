<?php

namespace App\Lib\Dashboarder;

use App\Entity\Shift;

class AdminUpcomingShifts
{
    private $router;
    private $entitymanager;
    private $twig;

    /*
     * I may need a lot more here. Too bad I can't use the container.
     */
    public function __construct($router, $entitymanager, $twig)
    {
        $this->router = $router;
        $this->entitymanager = $entitymanager;
        $this->twig = $twig;
    }

    public function dashize(\App\Entity\Person $user)
    {
        $shifts = $this->entitymanager->getRepository(Shift::class)
            ->findUpcoming(array('limit' => 15));
        return $this->twig->render('dashboarder/adminupcomingshifts.html.twig',
            array('shifts' => $shifts));
    }
}
