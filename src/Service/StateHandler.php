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

        if (class_exists('CustomBundle\Lib\StateHandlers\Job')) {
            $this->jobhandler = new \CustomBundle\Lib\StateHandlers\Job($em, $sm);
        } else {
            $this->jobhandler = new \App\Lib\StateHandlers\Job($em, $sm);
        }
        if (class_exists('CustomBundle\Lib\StateHandlers\Event')) {
            $this->eventhandler = new \CustomBundle\Lib\StateHandlers\Event($em, $sm);
        } else {
            $this->eventhandler = new \App\Lib\StateHandlers\Event($em, $sm);
        }
        if (class_exists('CustomBundle\Lib\StateHandlers\Shift')) {
            $this->shifthandler = new \CustomBundle\Lib\StateHandlers\Shift($em, $sm);
        } else {
            $this->shifthandler = new \App\Lib\StateHandlers\Shift($em, $sm);
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
