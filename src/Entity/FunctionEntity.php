<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

use App\Lib\ExternalEntityConfig;

/**
 * FunctionEntity
 *
 * Yes, this should be named "Function" since that's what it is supposed to be.
 * But feel free to try.
 *
 */
#[ORM\Entity(repositoryClass: \App\Repository\FunctionEntityRepository::class)]
#[UniqueEntity('name')]
#[ORM\Table(name: 'crewcall_function')]
#[Gedmo\Loggable]
class FunctionEntity
{
    // Not sure if I need it, but they might be useful.
    // (And i need it for migration for now.)
    use \BisonLab\CommonBundle\Entity\AttributesTrait;

    /**
     * @var integer
     */
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var string
     *
     */
    #[ORM\Column(name: 'name', type: 'string', length: 255, nullable: false, unique: true)]
    #[Gedmo\Versioned]
    private $name;

    /**
     * @var string
     *
     */
    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    private $description;

    /**
     * @var string $state
     *
     */
    #[ORM\Column(name: 'state', type: 'string', length: 40, nullable: true)]
    #[Assert\Choice(callback: 'getStatesList')]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private $state;

    /**
     * For the obvious use.
     */
    #[ORM\Column(type: 'boolean')]
    private $crew_manager = false;

    /**
     * If true, the user can pick this itself.
     */
    #[ORM\Column(type: 'boolean')]
    private $user_pickable = false;

    /**
     * This is for the non-connected functions.
     */
    #[ORM\OneToMany(targetEntity: \PersonFunction::class, mappedBy: 'function', cascade: ['remove'])]
    private $person_functions;

    /**
     * This is for the non-connected functions.
     */
    #[ORM\OneToMany(targetEntity: \Shift::class, mappedBy: 'function', cascade: ['remove'])]
    private $shifts;

    public function __construct($options = array())
    {
        $this->person_functions = new ArrayCollection();
        $this->shifts = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param string $name
     * @return FunctionEntity
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return FunctionEntity
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return Person
     */
    public function setState($state)
    {
        if ($state == $this->state) return $this;
        $state = strtoupper($state);
        if (!in_array($state, self::getStatesList())) {
            throw new \InvalidArgumentException(sprintf('The "%s" state is not a valid state.', $state));
        }
        $this->state = $state;
        return $this;
    }

    /**
     * Get state
     *
     * @return string 
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get state label
     *
     * @return string 
     */
    public function getStateLabel($state = null)
    {
        $state = $state ?: $this->getState();
        return ExternalEntityConfig::getStatesFor('FunctionEntity')[$state]['label'];
    }

    /**
     * Get states and a list of them.
     *
     * @return array 
     */
    public static function getStates()
    {
        return ExternalEntityConfig::getStatesFor('FunctionEntity');
    }
    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('FunctionEntity'));
    }

    public function getCrewManager(): bool
    {
        return $this->crew_manager;
    }

    public function setCrewManager(bool $crew_manager): self
    {
        $this->crew_manager = $crew_manager;

        return $this;
    }

    public function getUserPickable(): bool
    {
        return $this->user_pickable;
    }

    public function setUserPickable(bool $user_pickable): self
    {
        $this->user_pickable = $user_pickable;

        return $this;
    }

    /**
     * Add personFunction
     *
     * @param \App\Entity\PersonFunction $personFunction
     *
     * @return Person
     */
    public function addPersonFunction(\App\Entity\PersonFunction $personFunction)
    {
        $this->person_functions[] = $personFunction;

        return $this;
    }

    /**
     * Remove personFunction
     *
     * @param \App\Entity\PersonFunction $personFunction
     */
    public function removePersonFunction(\App\Entity\PersonFunction $personFunction)
    {
        $this->person_functions->removeElement($personFunction);
    }

    /**
     * Get personFunctions (AKA Skills)
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPersonFunctions()
    {
        return $this->person_functions;
    }

    /**
     * Add Shift
     *
     * @param \App\Entity\Shift $shift
     *
     * @return Shift
     */
    public function addShift(\App\Entity\Shift $shift)
    {
        if ($this->shifts->contains($shift))
            return $this;
        $this->shifts[] = $shift;
        $shift->setFunction($this);

        return $this;
    }

    /**
     * Remove Shift
     *
     * @param \App\Entity\Shift $shift
     */
    public function removeShift(\App\Entity\Shift $shift)
    {
        $this->shifts->removeElement($shift);
    }

    /**
     * Get Shifts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getShifts()
    {
        return $this->shifts;
    }

    public function __toString()
    {
        return $this->getName();
    }

    /*
     * Helper functions.
     */

    /**
     * Get People
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPeople($active_only = true)
    {
        $people = new ArrayCollection();
        foreach ($this->person_functions as $pf) {
            if ($active_only && !in_array($pf->getPerson()->getState(),
                    ExternalEntityConfig::getActiveStatesFor('Person')))
                continue;
            if (!$people->contains($pf->getPerson()))
                $people->add($pf->getPerson());
        }
        return $people;
    }

    /*
     * Many ways of counting, this is kinda resourceeating, but useful and
     * hopefully not too bad. If we do get performance issues we'd find out
     * hopefully and stop using it where it hurts.
     *
     * * No options: Just count personfunctions.
     * * 'by_state': Count getPeople() and sort by state.
     */
    public function countPeople($options = [])
    {
        // The simplest one.
        if (empty($options))
            return $this->personfunctions->count();
        if ($options['by_state'] ?? false) {
            $states = [];
            foreach ($this->getPeople(false) as $p) {
                if (!isset($states[$p->getState()]))
                    $states[$p->getState()] = 1;
                else
                    $states[$p->getState()]++;
            }
            return $states;
        }
    }

    public function isDeleteable()
    {
        if ($this->getShifts()->count() > 0)
            return false;     
        return true;
    }
    
}
