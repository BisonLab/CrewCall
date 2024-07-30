<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * App\Entity\LocationContext
 */
#[ORM\Entity(repositoryClass: \App\Repository\LocationContextRepository::class)]
#[ORM\Table(name: 'crewcall_locationcontext')]
class LocationContext
{
    use \BisonLab\ContextBundle\Entity\ContextBaseTrait;
    /**
     * @var mixed
     */
    #[ORM\ManyToOne(targetEntity: \Location::class, inversedBy: 'contexts')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private $owner;

    public function getOwnerEntityAlias()
    {
        return "App:Location";
    }
}
