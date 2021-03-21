<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

use App\Entity\Organization;

/**
 *
 */
class OrganizationRepository extends ServiceEntityRepository
{
    use \BisonLab\CommonBundle\Entity\ContextRepositoryTrait;

    private $params;

    public function __construct(ManagerRegistry $registry, ContainerBagInterface $params)
    {
        $this->params = $params;
        parent::__construct($registry, Organization::class);
    }

    public function getInternalOrganization()
    {
        $io_config = $this->params->get('internal_organization');
        return $this->findOneBy(['name' => $io_config['name']]);
    }

    /* This is very common for all repos. Could be in a trait aswell. */
    public function searchByField($field, $value)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('p')
            ->from($this->_entityName, 'p')
            ->where("lower(p." . $field . ") like ?1")
            ->orWhere("upper(p." . $field . ") like ?2")
            ->setParameter(1, '%' . mb_strtolower($value) . '%')
            ->setParameter(2, '%' . mb_strtoupper($value) . '%');

        return $qb->getQuery()->getResult();
    }
}
