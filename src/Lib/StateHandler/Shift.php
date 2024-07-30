<?php

namespace App\Lib\StateHandler;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Shift as ShiftEntity;

/*
 */
class Shift
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getStateHandleClass()
    {
        return ShiftEntity::class;
    }

    public function handle(\App\Entity\Shift $shift, $from, $to)
    {
        if ($to == "COMPLETED") {
            foreach ($shift->getJobs(['booked' => true]) as $job) {
                $job->setState("COMPLETED");
                if ($from === false)
                    continue;
                $this->entityManager->persist($job);
                $meta = $this->entityManager->getClassMetadata(get_class($job));
                $uow = $this->entityManager->getUnitOfWork();
                $uow->recomputeSingleEntityChangeSet($meta, $job);
            }
        }
    }
}
