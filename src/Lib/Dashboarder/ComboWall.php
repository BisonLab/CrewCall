<?php

namespace App\Lib\Dashboarder;
use Twig\Environment as Twig;

class ComboWall
{
    public function __construct(
        private Twig $twig
    ) {
    }

    public function dashize($user)
    {
        return $this->twig->render('dashboarder/combowall.html.twig');
    }
}
