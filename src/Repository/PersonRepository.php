<?php

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @method Person|null find($id, $lockMode = null, $lockVersion = null)
 * @method Person|null findOneBy(array $criteria, array $orderBy = null)
 * @method Person[]    findAll()
 * @method Person[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    use \BisonLab\ContextBundle\Repository\ContextOwnerRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }


    public function findOneByUsernameOrEmail(string $identifier): ?Person
    {
        if ($res = $this->createQueryBuilder('p')
            ->where('p.username = :username')
            ->orWhere('p.email = :email')
            ->setParameter('username', $identifier)
            ->setParameter('email', $identifier)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
            )
            return current($res);
        else
            return null;
        // For later use, when we have a newer Doctrine
        //    ->getOneOrNullResult()
    }
    public function findWithRoles()
    {
        $found = new \Doctrine\Common\Collections\ArrayCollection();

        // Had issues with distinct, dropped it.
        $qb = $this->_em->createQueryBuilder();
        $qb->select('p')
            ->from($this->_entityName, 'p')
            ->innerJoin('p.person_role_organizations', 'pfo');
        foreach ($qb->getQuery()->getResult() as $per) {
            $found->add($per);
        }

        $qb2 = $this->_em->createQueryBuilder();
        $qb2->select('p')
            ->from($this->_entityName, 'p')
            ->innerJoin('p.person_role_events', 'pfe');
        foreach ($qb2->getQuery()->getResult() as $per) {
            if ($found->contains($per))
                continue;
            $found->add($per);
        }

        $qb3 = $this->_em->createQueryBuilder();
        $qb3->select('p')
            ->from($this->_entityName, 'p')
            ->innerJoin('p.person_role_locations', 'pfl');
        foreach ($qb3->getQuery()->getResult() as $per) {
            if ($found->contains($per))
                continue;
            $found->add($per);
        }

        return $found;
    }

    public function findByState($state, $from = null, $to = null)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('p')
            ->from($this->_entityName, 'p')
            ->innerJoin('p.person_states', 'ps')
            ->where("ps.state = :state")
            ->setParameter("state", $state);

        // Between.
        if ($from && $to) {
            $from = new \DateTime($from);
            $to   = new \DateTime($to);
            $qb->andWhere('ps.from_date <= :to_date')
                ->setParameter('to_date', $to);
            $qb->andWhere('ps.to_date >= :from_date OR ps.to_date is null')
                ->setParameter('from_date', $from);
        // Not sure if these works. 50/50 and me..
        } elseif ($from) {
            $from = new \DateTime($options['from_date']);
            $qb->andWhere('ps.from_date <= :from_date')
                ->setParameter('from_date', $from);
        } elseif ($to) {
            $to = new \DateTime($options['to_date']);
            $qb->andWhere('ps.to_date <= :to_date OR ps.to_date is null')
                ->setParameter('to_date', $to);
        }
        return $qb->getQuery()->getResult();
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

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newEncodedPassword): void
    {
        if (!$user instanceof Person) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newEncodedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }
}
