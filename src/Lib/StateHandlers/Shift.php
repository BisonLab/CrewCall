<?php

namespace App\Lib\StateHandlers;

/*
 */
class Shift
{
    private $em;
    private $sm;

    public function __construct($em, $sm)
    {
        $this->em = $em;
        $this->sm = $sm;
    }

    public function handle(\App\Entity\Shift $shift, $from, $to)
    {
        if ($to == "COMPLETED") {
            foreach ($shift->getJobs() as $job) {
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
