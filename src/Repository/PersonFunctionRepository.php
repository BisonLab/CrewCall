<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

use App\Entity\PersonFunction;

/**
 *
 */
class PersonFunctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, ContainerBagInterface $params)
    {
        $this->params = $params;
        parent::__construct($registry, PersonFunction::class);
    }
}
