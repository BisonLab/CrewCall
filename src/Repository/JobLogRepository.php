<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\JobLog;

/**
 *
 */
class JobLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobLog::class);
    }

    public function checkOverlapForPerson($joblog)
    {
        $person = $joblog->getJob()->getPerson();
        $from = $joblog->getIn();
        $to = $joblog->getOut();
        $qb = $this->_em->createQueryBuilder();
        $qb->select('jl')
            ->from($this->_entityName, 'jl')
            ->innerJoin('jl.job', 'j')
            ->where("j.person = :person")
            ->andWhere('jl.in <= :to')
            ->andWhere('jl.out >= :from')
            ->setParameter('person', $person)
            ->setParameter('to', $to)
            ->setParameter('from', $from);
        return $qb->getQuery()->getResult();
    }
}
