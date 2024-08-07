<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * App\Entity\PersonContext
 */
#[ORM\Entity(repositoryClass: \App\Repository\PersonContextRepository::class)]
#[ORM\Table(name: 'crewcall_personcontext')]
class PersonContext
{
    use \BisonLab\ContextBundle\Entity\ContextBaseTrait;
    /**
     * @var mixed
     */
    #[ORM\ManyToOne(targetEntity: \Person::class, inversedBy: 'contexts')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private $owner;

    public function getOwnerEntityAlias()
    {
        return "App:Person";
    }
}
