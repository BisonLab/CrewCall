<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Event;
use App\Entity\Person;
use App\Entity\Role;

/**
 */
#[ORM\Entity(repositoryClass: \App\Repository\PersonRoleEventRepository::class)]
#[ORM\Table(name: 'crewcall_person_role_event')]
class PersonRoleEvent
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\ManyToOne(targetEntity: \Person::class, inversedBy: 'person_role_events')]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: false)]
    private $person;

    #[ORM\ManyToOne(targetEntity: \Role::class, inversedBy: 'person_role_events')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false)]
    private $role;

    #[ORM\ManyToOne(targetEntity: \Event::class, inversedBy: 'person_role_events')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', nullable: false)]
    private $event;

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
            $this->person->removeEventRole($this);
        }

        if ($person !== null) {
            $person->addPersonRoleEvent($this);
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
            $this->role->removePersonRoleEvent($this);
        }

        if ($role !== null) {
            $role->addPersonRoleEvent($this);
        }

        $this->role = $role;
        return $this;
    }

    /**
     * Set event
     *
     * @param \App\Entity\Event $event
     *
     * @return PersonRoleEvent
     */
    public function setEvent(Event $event)
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Get event
     *
     * @return \App\Entity\Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    public function getRoleName()
    {
        return (string)$this->getRole();
    }

    public function __toString()
    {
        return (string)$this->getRole() . " at " . (string)$this->getEvent();
    }
}
