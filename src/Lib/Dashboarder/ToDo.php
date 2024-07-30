<?php

namespace App\Lib\Dashboarder;

use Twig\Environment as Twig;

class ToDo
{
    public function __construct(
        private Twig $twig
    ) {
    }

    public function dashize($user)
    {
        return $this->twig->render('dashboarder/todo.html.twig');
    }
}
