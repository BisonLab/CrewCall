<?php

namespace App\Lib\StateHandler;

/*
 */
class Shift
{
    private $em;
    private $sm;

    public function __construct($container)
    {
        $this->em = $container->get('doctrine')->getManager();
        $this->sm = $container->get('sakonnin.messages');
    }

    public function handle(\App\Entity\Shift $shift, $from, $to)
    {
        if ($to == "COMPLETED") {
            foreach ($shift->getJobs(['booked' => true]) as $job) {
                $job->setState("COMPLETED");
                if ($from === false)
                    continue;
                $this->em->persist($job);
                $meta = $this->em->getClassMetadata(get_class($job));
                $uow = $this->em->getUnitOfWork();
                $uow->recomputeSingleEntityChangeSet($meta, $job);
            }
        }
    }
}
