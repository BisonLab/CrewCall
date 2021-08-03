<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\Shift;
use App\Lib\ExternalEntityConfig;

/**
 *
 */
class ShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shift::class);
    }

    /*
     * TODO: Add timeframe and default with from now
     */
    public function findUpcoming($options = array())
    {
        // Unless there are a set timeframe, use "from now".
        $from = new \DateTime();
        if (isset($options['from'])) {
            if ($options['from'] instanceof \DateTime )
                $from = $options['from'];
            else
                $from = new \DateTime($options['from']);
        }

        $qb = $this->_em->createQueryBuilder();
        $qb->select('s')->from($this->_entityName, 's');

        // We want to include running shifts. In case of late needs.
        // But also have an option not to.
        if ($options['without_rollover'] ?? false) {
            $qb->where('s.start >= :from');
        } else {
            $qb->where(
                $qb->expr()->orX(
                    $qb->expr()->gte('s.start', ':from'),
                    $qb->expr()->between(':from', 's.start', 's.end')
               ) );
        }
        $qb->setParameter('from', $from);

        if (isset($options['to'])) {
            if ($options['to'] instanceof \DateTime )
                $to = $options['to'];
            else
                $to = new \DateTime($options['to']);
            $qb->andWhere('s.end <= :to')
               ->setParameter('to', $to);
        }

        // There are a few options here. Well, one for now, "open".
        if ($options['open'] ?? false) {
            $states = ExternalEntityConfig::getOpenStatesFor('Shift');
            $qb->andWhere('s.state in (:states)')
               ->setParameter('states', $states);
        }
        if (isset($options['limit']))
            $qb->setMaxResults($options['limit']);
        $qb->orderBy('s.start', 'ASC');
        return $qb->getQuery()->getResult();
    }

    public function findUpcomingForFunctions(array $functions, $options = array())
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s')
            ->from($this->_entityName, 's')
            ->where("s.function in (:functions)")
            ->setParameter('functions', $functions);

        // Unless there are a set timeframe, use "from now".
        $from = new \DateTime();
        if (isset($options['from'])) {
            if ($options['from'] instanceof \DateTime )
                $from = $options['from'];
            else
                $from = new \DateTime($options['from']);
            // Doublecheck, there are no upcoming in the past.
            if ($from < new \DateTime())
                $from = new \DateTime();
        }
        // We want to include running shifts. In case of late needs.
        $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->gte('s.start', ':from'),
                    $qb->expr()->between(':from', 's.start', 's.end')
               ) )
           ->setParameter('from', $from);


        if (isset($options['to'])) {
            if ($options['to'] instanceof \DateTime )
                $to = $options['to'];
            else
                $to = new \DateTime($options['to']);
            $qb->andWhere('s.end <= :to')
               ->setParameter('to', $to);
        }

        // There are a few options here. Well, one for now, "booked".
        if ($options['booked'] ?? false) {
            $states = ExternalEntityConfig::getBookedStatesFor('Shift');
            $qb->andWhere('s.state in (:states)')
               ->setParameter('states', $states);
        }
        $qb->orderBy('s.start', 'ASC');
        return $qb->getQuery()->getResult();
    }
}
