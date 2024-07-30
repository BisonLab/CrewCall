<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

use App\Entity\EmbeddableAddress;
use App\Lib\ExternalEntityConfig;

/**
 * Location
 *
 */
#[ORM\Entity(repositoryClass: \App\Repository\LocationRepository::class)]
#[UniqueEntity('name')]
#[ORM\Table(name: 'crewcall_location')]
#[Gedmo\Loggable]
class Location
{
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
    #[Gedmo\Versioned]
    #[Assert\Choice(callback: 'getStatesList')]
    private $state = "OPEN";

    /**
     * @var string
     *
     */
    #[ORM\Column(name: 'phone_number', type: 'string', length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private $phone_number;

    #[ORM\Embedded(class: \EmbeddableAddress::class)]
    private $address;

    /**
     * @var string
     *
     * Kinda alternative to address.
     *
     */
    #[ORM\Column(name: 'map_link', type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    private $map_link;

    /* 
     * Locations. Not to be confused with addresses since a location can be
     * "The bar in the left corner of stage 2" or "The tent at field 4".
     *
     * I could instead do a group table with childs and parents and so on and
     * then connect these locations against one or the other.  And I may well
     * do that if this ends up being too odd for the users or code.
     */
    #[ORM\OneToMany(targetEntity: \Location::class, mappedBy: 'parent', fetch: 'EXTRA_LAZY', cascade: ['persist', 'remove', 'merge'], orphanRemoval: true)]
    private $children;

    /**
     */
    #[ORM\ManyToOne(targetEntity: \Location::class, inversedBy: 'children')]
    #[Gedmo\Versioned]
    #[ORM\JoinColumn(name: 'parent_location_id', referencedColumnName: 'id')]
    private $parent;

    #[ORM\OneToMany(targetEntity: \Event::class, mappedBy: 'location')]
    private $events;

    #[ORM\OneToMany(targetEntity: \Shift::class, mappedBy: 'location')]
    private $shifts;

    #[ORM\OneToMany(targetEntity: \PersonRoleLocation::class, mappedBy: 'location', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $person_role_locations;

    #[ORM\OneToMany(targetEntity: \LocationContext::class, mappedBy: 'owner', cascade: ['persist', 'remove', 'merge'], orphanRemoval: true)]
    private $contexts;

    public function __construct($options = array())
    {
        $this->children = new ArrayCollection();
        $this->address = new EmbeddableAddress();
        $this->events  = new \Doctrine\Common\Collections\ArrayCollection();
        $this->shifts  = new \Doctrine\Common\Collections\ArrayCollection();
        $this->person_role_locations = new ArrayCollection();
        $this->contexts = new ArrayCollection();
    }
    
    /*
     * Automatically generated getters and setters below this
     */
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
     * @return Location
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
     * @return Location
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
     * Set phone_number
     *
     * @param string $phone_number
     * @return Location
     */
    public function setPhoneNumber($phone_number)
    {
        $this->phone_number = $phone_number;

        return $this;
    }

    /**
     * Get phone_number
     *
     * @return string 
     */
    public function getPhoneNumber()
    {
        return $this->phone_number;
    }

    /**
     * Set map_link
     *
     * @param string $map_link
     * @return Location
     */
    public function setMapLink($map_link)
    {
        $this->map_link = $map_link;

        return $this;
    }

    /**
     * Get map_link
     *
     * @return string 
     */
    public function getMapLink()
    {
        return $this->map_link;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return Location
     */
    public function setState($state)
    {
        if ($state == $this->state) return $this;
        if (is_int($state)) { $state = self::getStates()[$state]; }
        $state = strtoupper($state);
        if (!isset(self::getStates()[$state])) {
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
        return ExternalEntityConfig::getStatesFor('Location')[$state]['label'];
    }

    /**
     * Get states
     *
     * @return array 
     */
    public static function getStates()
    {
        return ExternalEntityConfig::getStatesFor('Location');
    }
    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('Location'));
    }

    /**
     * Add child
     *
     * @param \App\Entity\Location $child
     * @return Location
     */
    public function addChild(\App\Entity\Location $child)
    {
        $this->children[] = $child;
        $child->setParent($this);

        return $this;
    }

    /**
     * Remove children
     *
     * @param \App\Entity\Location $child
     */
    public function removeChild(\App\Entity\Location $child)
    {
        $this->children->removeElement($child);
    }

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set parent
     *
     * @param \App\Entity\Location $parent
     * @return Location
     */
    public function setParent(\App\Entity\Location $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \App\Entity\Location 
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set address
     *
     * @param \App\Entity\Address $address
     *
     * @return Location
     */
    public function setAddress(EmbeddableAddress $address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address
     *
     * @return \App\Entity\EmbeddableAddress
     */
    public function getAddress()
    {
        /*
         * There is always an embeddable address here, alas if it's basically
         * empty we'd better use the parents address.
         */
        if (!$this->getParent())
            // Just do it.
            return $this->address;
        // Then check it.
        if ($this->address->isEmpty())
            return $this->getParent()->getAddress();
        return $this->address;
    }

    /*
     * The big "Does this work or not" is wether this getter should include
     * *all* functions. Alas also those in the person_* tables. I say "Yes", but
     * then it's not that easy to handle these functions in picker-forms.
     */
    public function getPersonRoleLocations()
    {
        return $this->person_role_locations;
    }

    public function addPersonRoleLocation(PersonRoleLocation $pfo)
    {
        if (!$this->person_role_locations->contains($pfo)) {
            $this->person_role_locations->add($pfo);
        }
        return $this;
    }

    public function removePersonRoleLocation(PersonRoleLocation $pfo)
    {
        if ($this->person_role_locations->contains($pfo)) {
            $this->person_role_locations->removeElement($pfo);
        }
        return $this;
    }

    /*
     * The downside of this "helper" is that we don't see the function, aka
     * what they do in the location.
     */
    public function getPeople()
    {
        $persons = new ArrayCollection();
        foreach ($this->getPersonRoleLocations() as $prl) {
            if (!$persons->contains($prl->getPerson()))
                $persons->add($prl->getPerson());
        } 
        return $persons;
    }

    /**
     * Get events
     *
     * @return objects 
     */
    public function getEvents($sort_order = null)
    {
        if ($sort_order == "ASC") {
            $iterator = $this->events->getIterator();
            $iterator->uasort(function ($a, $b) {
                if ($a->getStart()->format("U") == $b->getStart()->format("U")) return 0;
                return ($a->getStart()->format("U") < $b->getStart()->format("U")) ? -1 : 1;
            });
            return new ArrayCollection(iterator_to_array($iterator));
        }
        if ($sort_order == "DESC") {
            $iterator = $this->events->getIterator();
            $iterator->uasort(function ($a, $b) {
                if ($a->getStart()->format("U") == $b->getStart()->format("U")) return 0;
                return ($a->getStart()->format("U") > $b->getStart()->format("U")) ? -1 : 1;
            });
            return new ArrayCollection(iterator_to_array($iterator));
        }
        return $this->events;
    }

    /**
     * Get contexts
     *
     * @return objects
     */
    public function getContexts()
    {
        return $this->contexts;
    }

    /**
     * add context
     *
     * @return mixed
     */
    public function addContext(LocationContext $context)
    {
        $this->contexts[] = $context;
        $context->setOwner($this) ;
    }

    /**
     * Remove contexts
     *
     * @param LocationContext $contexts
     */
    public function removeContext(LocationContext $contexts)
    {
        $this->contexts->removeElement($contexts);
    }

    /**
     * Is this deleeteable? If any event connected to it, no.
     *
     * @return boolean
     */
    public function isDeleteable()
    {
        return count($this->getEvents()) == 0;
    }

    public function __toString()
    {
        if ($this->getParent())
            return (string)$this->getParent() . " -> " . $this->getName();

        return $this->getName();
    }

    /*
     * "Location tree" stuff.
     */
    public function getMainLocation()
    {
        if ($this->getParent())
            return $this->getParent()->getMainLocation();
        return $this;
    }

    /*
     * You better as the MainLocation if you want'em all.
     * But this will go down the tree, not just the children.
     */
    public function getSubLocations()
    {
        $sublocations = $this->getChildren()->toArray();
        foreach ($this->getChildren() as $child) {
            $sublocations = array_merge($sublocations, $child->getSubLocations()->toArray());
        }

        return new ArrayCollection($sublocations);
    }
}
