<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * App\Entity\OrganizationContext
 */
#[ORM\Entity(repositoryClass: \App\Repository\OrganizationContextRepository::class)]
#[ORM\Table(name: 'crewcall_organizationcontext')]
class OrganizationContext
{
    use \BisonLab\ContextBundle\Entity\ContextBaseTrait;
    /**
     * @var mixed
     */
    #[ORM\ManyToOne(targetEntity: \Organization::class, inversedBy: 'contexts')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private $owner;

    public function getOwnerEntityAlias()
    {
        return "App:Organization";
    }
}
