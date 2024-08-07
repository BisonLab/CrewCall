<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 */
#[ORM\Entity(repositoryClass: \App\Repository\PersonRoleOrganizationRepository::class)]
#[ORM\Table(name: 'crewcall_person_role_organization')]
class PersonRoleOrganization
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\ManyToOne(targetEntity: \Person::class, inversedBy: 'person_role_organizations')]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: false)]
    private $person;

    #[ORM\ManyToOne(targetEntity: \Role::class, inversedBy: 'person_role_organizations')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false)]
    private $role;

    #[ORM\ManyToOne(targetEntity: \Organization::class, inversedBy: 'person_role_organizations')]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private $organization;

    /**
     * @var string
     *
     */
    #[ORM\Column(name: 'from_date', type: 'date', nullable: false)]
    private $from_date;

    /**
     * @var string
     *
     */
    #[ORM\Column(name: 'to_date', type: 'date', nullable: true)]
    private $to_date;

    public function __construct()
    {
        $this->from_date = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPerson()
    {
        return $this->person;
    }

    public function setPerson(Person $person = null)
    {
        if ($this->person !== null) {
            $this->person->removePersonRoleOrganization($this);
        }

        if ($person !== null) {
            $person->addPersonRoleOrganization($this);
        }

        $this->person = $person;
        return $this;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setRole(Role $role = null)
    {
        if ($this->role !== null) {
            $this->role->removePersonRoleOrganization($this);
        }

        if ($role !== null) {
            $role->addPersonRoleOrganization($this);
        }

        $this->role = $role;
        return $this;
    }

    /**
     * Set fromDate
     *
     * @param \DateTime $fromDate
     *
     * @return PersonRoleOrganization
     */
    public function setFromDate($fromDate)
    {
        $this->from_date = $fromDate;

        return $this;
    }

    /**
     * Get fromDate
     *
     * @return \DateTime
     */
    public function getFromDate()
    {
        return $this->from_date;
    }

    /**
     * Set toDate
     *
     * @param \DateTime $toDate
     *
     * @return PersonRoleOrganization
     */
    public function setToDate($toDate)
    {
        $this->to_date = $toDate;

        return $this;
    }

    /**
     * Get toDate
     *
     * @return \DateTime
     */
    public function getToDate()
    {
        return $this->to_date;
    }

    /**
     * Set organization
     *
     * @param \App\Entity\Organization $organization
     *
     * @return PersonRoleOrganization
     */
    public function setOrganization(\App\Entity\Organization $organization)
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * Get organization
     *
     * @return \App\Entity\Organization
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    public function getRoleName()
    {
        return (string)$this->getRole();
    }

    public function __toString()
    {
        return (string)$this->getRole() . " at " . (string)$this->getOrganization();
    }
}
