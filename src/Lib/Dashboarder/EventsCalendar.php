<?php

namespace App\Lib\Dashboarder;

use Twig\Environment as Twig;

class EventsCalendar
{
    public function __construct(
        private Twig $twig
    ) {
    }

    public function dashize(\App\Entity\Person $user)
    {
        return $this->twig->render('dashboarder/events_calendar.html.twig');
    }
}
