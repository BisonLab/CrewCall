<?php

namespace App\Entity;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

/**
 * App\Entity\PersonContext
 *
 * @ORM\Table(name="crewcall_personcontext")
 * @ORM\Entity(repositoryClass="App\Repository\PersonContextRepository")
 */
class PersonContext
{
    use \BisonLab\CommonBundle\Entity\ContextBaseTrait;
    /**
     * @var mixed
     *
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="contexts")
     * @ORM\JoinColumn(name="owner_id", referencedColumnName="id", nullable=false)
     */
    private $owner;

    public function getOwnerEntityAlias()
    {
        return "App:Person";
    }
}
