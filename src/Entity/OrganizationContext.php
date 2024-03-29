<?php

namespace App\Entity;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

/**
 * App\Entity\OrganizationContext
 *
 * @ORM\Table(name="crewcall_organizationcontext")
 * @ORM\Entity(repositoryClass="App\Repository\OrganizationContextRepository")
 */
class OrganizationContext
{
    use \BisonLab\ContextBundle\Entity\ContextBaseTrait;
    /**
     * @var mixed
     *
     * @ORM\ManyToOne(targetEntity="Organization", inversedBy="contexts")
     * @ORM\JoinColumn(name="owner_id", referencedColumnName="id", nullable=false)
     */
    private $owner;

    public function getOwnerEntityAlias()
    {
        return "App:Organization";
    }
}
