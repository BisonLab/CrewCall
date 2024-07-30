<?php

namespace App\Lib\Dashboarder;

use Twig\Environment as Twig;
use Doctrine\ORM\EntityManagerInterface;

class Search
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Twig $twig
    ) {
    }

    public function dashize($user)
    {
        return $this->twig->render('dashboarder/search.html.twig');
    }
}
