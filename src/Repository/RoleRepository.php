<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

use App\Entity\Role;

/**
 *
 */
class RoleRepository extends ServiceEntityRepository
{
    private $params;

    public function __construct(ManagerRegistry $registry, ContainerBagInterface $params)
    {
        $this->params = $params;
        parent::__construct($registry, Role::class);
    }

    public function getDefaultRole()
    {
        $io_config = $this->params->get('internal_organization');
        return $this->findOneBy(['name' => $io_config['default_role']]);
    }
    public function findAll()
    {
        return $this->findBy(array(), array('name' => 'ASC'));
    }

    public function findAllActive()
    {
        return $this->findBy(array('state' => ExternalEntityConfig::getActiveStatesFor('Role')), array('name' => 'ASC'));
    }

    public function findNamesWithPeopleCount()
    {
        $query = $this->_em->createQuery('SELECT r.id, re.name, count(pr.id) as people FROM ' . $this->_entityName . ' r JOIN r.person_roles pr GROUP BY r.id');
        return $result = $query->getResult();
    }
}
