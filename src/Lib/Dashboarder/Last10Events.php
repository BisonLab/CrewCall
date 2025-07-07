<?php

namespace App\Lib\Dashboarder;

use Twig\Environment as Twig;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Event;

class Last10Events
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Twig $twig
    ) {
    }

    public function dashize($user)
    {
        $events = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Event::class, 'e')
            ->where('e.parent is null')
            ->orderBy('e.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->twig->render('dashboarder/last10events.html.twig',
            array('events' => $events));
    }
}
