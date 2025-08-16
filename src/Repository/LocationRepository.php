<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\Location;

/**
 *
 */
class LocationRepository extends ServiceEntityRepository
{
    use \BisonLab\ContextBundle\Repository\ContextOwnerRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /* This is very common for all repos. Could be in a trait aswell. */
    public function searchByField($field, $value)
    {
        $qb = $this->createQueryBuilder('l');
        $qb->where("lower(l." . $field . ") like ?1")
            ->orWhere("upper(l." . $field . ") like ?2")
            ->setParameter(1, '%' . mb_strtolower($value) . '%')
            ->setParameter(2, '%' . mb_strtoupper($value) . '%');

        return $qb->getQuery()->getResult();
    }
}
