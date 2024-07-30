<?php

namespace App\Lib\Dashboarder;

use App\Entity\Shift;
use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;

class AdminUpcomingShifts
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \Twig\Environment $twig
    ) {
    }

    public function dashize(\App\Entity\Person $user)
    {
        $shifts = $this->entitymanager->getRepository(Shift::class)
            ->findUpcoming(array('limit' => 15));
        return $this->twig->render('dashboarder/adminupcomingshifts.html.twig',
            array('shifts' => $shifts));
    }
}
