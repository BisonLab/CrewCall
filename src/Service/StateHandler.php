<?php

namespace App\Service;

use App\Entity\Job;
use App\Entity\Event;
use App\Entity\Shift;

class StateHandler
{
    private $em;
    private $sm;
    private $jobhandler;
    private $eventhandler;
    private $shifthandler;

    public function __construct($em, $sm)
    {
        $this->em = $em;
        $this->sm = $sm;

        if (class_exists('CustomBundle\Lib\StateHandler\Job')) {
            $this->jobhandler = new \CustomBundle\Lib\StateHandler\Job($em, $sm);
        } else {
            $this->jobhandler = new \App\Lib\StateHandler\Job($em, $sm);
        }
        if (class_exists('CustomBundle\Lib\StateHandler\Event')) {
            $this->eventhandler = new \CustomBundle\Lib\StateHandler\Event($em, $sm);
        } else {
            $this->eventhandler = new \App\Lib\StateHandler\Event($em, $sm);
        }
        if (class_exists('CustomBundle\Lib\StateHandler\Shift')) {
            $this->shifthandler = new \CustomBundle\Lib\StateHandler\Shift($em, $sm);
        } else {
            $this->shifthandler = new \App\Lib\StateHandler\Shift($em, $sm);
        }
    }

    public function handleStateChange($entity, $from, $to)
    {
        if ($from == $to)
            return;

        if ($entity instanceof Job) {
            $this->jobhandler->handle($entity, $from, $to);
        }
        if ($entity instanceof Event) {
            $this->eventhandler->handle($entity, $from, $to);
        }
        if ($entity instanceof Shift) {
            $this->shifthandler->handle($entity, $from, $to);
        }
        return;
    }
}
