<?php

namespace App\Repository;

use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Common\Collections\ArrayCollection;
use App\Lib\ExternalEntityConfig;

/**
 *
 */
class FunctionEntityRepository extends \Doctrine\ORM\EntityRepository
{
    // I wonder if this can work out as a good idea. Point is to use in the
    // forms for returning the query buillder with the query from here instead
    // of the result, which it does not like.
    private $return_qb = false;

    public function setReturnQb($val = true)
    {
        $this->return_qb = $val;
    }

    public function findAll(): array
    {
        return $this->findBy(array(), array('name' => 'ASC'));
    }

    public function findAllActive()
    {
        return $this->findBy(array('state' => ExternalEntityConfig::getActiveStatesFor('FunctionEntity')), array('name' => 'ASC'));
    }

    public function findNamesWithPeopleCount()
    {
        $query = $this->createQuery('SELECT fe.id, fe.name, count(pf.id) as people FROM ' . $this->_entityName . ' fe JOIN fe.person_functions pf GROUP BY fe.id');
        return $result = $query->getResult();
    }

    public function findPickableFunctions()
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where("f.user_pickable = :user_pickable")
            ->setParameter('user_pickable', true);
        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function searchByField($field, $value)
    {
        if ($field == 'attributes') {
            if (is_array($value)) {
                $afield = strtolower($value[0]);
                $avalue = $value[1];
            } else {
                list($afield, $avalue) = explode(":", $value);
            }
            $rsm = new ResultSetMapping;
            $rsm->addEntityResult('App\Entity\FunctionEntity', 'cf');
            $rsm->addFieldResult('cf', 'id', 'id');
            $sql = "select id from crewcall_function where attributes->>'" . $afield . "'='" . $avalue . "';";
            $query = $this->createNativeQuery($sql, $rsm);
            $ids = $query->getResult();
            if ($ids) {
                // Have to clear the result cache so we can get a complete
                // entity and not one with just the ID we asked for.
                // (I'm lazy and not botheres with adding all fields in the
                // select.
                $this->clear();
                // Gotta find the whole object then.
                return $this->find($ids[0]->getId());
            }
            return null;
        } else {
            $qb = $this->createQueryBuilder('f');
            $qb->where("lower(f." . $field . ") like ?1")
                ->orWhere("upper(f." . $field . ") like ?2")
                ->setParameter(1, '%' . mb_strtolower($value) . '%')
                ->setParameter(2, '%' . mb_strtoupper($value) . '%');
            return $qb->getQuery()->getResult();
        }
    }
}
