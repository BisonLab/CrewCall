<?php

namespace App\Service;

use App\Entity\Job;
use App\Entity\Event;
use App\Entity\Shift;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ServiceLocator;
use BisonLab\SakonninBundle\Service\Messages as SakonninMessages;

class StateHandler
{
    private $container;
    private $jobhandler;
    private $eventhandler;
    private $shifthandler;

    public function __construct($container
    ) {
        if (class_exists('CustomBundle\Lib\StateHandler\Job')) {
            $this->jobhandler = new \CustomBundle\Lib\StateHandler\Job($container);
        } else {
            $this->jobhandler = new \App\Lib\StateHandler\Job($container);
        }
        if (class_exists('CustomBundle\Lib\StateHandler\Event')) {
            $this->eventhandler = new \CustomBundle\Lib\StateHandler\Event($container);
        } else {
            $this->eventhandler = new \App\Lib\StateHandler\Event($container);
        }
        if (class_exists('CustomBundle\Lib\StateHandler\Shift')) {
            $this->shifthandler = new \CustomBundle\Lib\StateHandler\Shift($container);
        } else {
            $this->shifthandler = new \App\Lib\StateHandler\Shift($container);
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
