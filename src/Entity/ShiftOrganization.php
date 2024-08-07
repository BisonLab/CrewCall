<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

use App\Lib\ExternalEntityConfig;

/**
 * This is the "An organization provides five roadies" equivalent of Job, which
 * is the connection between one individual and a function in a shift.
 *
 */
#[ORM\Entity(repositoryClass: \App\Repository\ShiftOrganizationRepository::class)]
#[ORM\Table(name: 'crewcall_shift_organization')]
#[Gedmo\Loggable]
class ShiftOrganization
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\ManyToOne(targetEntity: \Shift::class, inversedBy: 'shift_organizations')]
    #[ORM\JoinColumn(name: 'shift_id', referencedColumnName: 'id', nullable: false)]
    private $shift;

    #[ORM\ManyToOne(targetEntity: \Organization::class, inversedBy: 'shift_organizations')]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private $organization;

    /**
     * @var string
     *
     */
    #[ORM\Column(name: 'amount', type: 'integer', nullable: false)]
    #[Gedmo\Versioned]
    private $amount;

    /**
     * @var string $state
     *
     */
    #[ORM\Column(name: 'state', type: 'string', length: 40, nullable: true)]
    #[Gedmo\Versioned]
    #[Assert\Choice(callback: 'getStatesList')]
    private $state;

    public function getId()
    {
        return $this->id;
    }

    /**
     * Set amount
     *
     * @param integer $amount
     *
     * @return ShiftOrganization
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return integer
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Get the amount of persons booked. It's either / or here. I am not
     * going to make support for "We have five of the ten you need" until
     * we are certain we want and need it.
     *
     * It can be solved by not adding more than the booked amount in the
     * amount field and setting the state to a booked state.
     *
     * (I considered just having an amount of confirmed/booked and not having
     * any state here, but that may be too inflexible.)
     *
     * @return integer
     */
    public function getBookedAmount()
    {
        if (in_array($this->getState(), $this->getStates()['booked_states']))
            return $this->amount;
        else
            return 0;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return Event
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
        return $this->state ?: "CONFIRMED";
    }

    /**
     * Get state label
     *
     * @return string 
     */
    public function getStateLabel($state = null)
    {
        $state = $state ?: $this->getState();
        return ExternalEntityConfig::getStatesFor('ShiftOrganization')[$state]['label'];
    }

    /**
     * Get states
     *
     * @return array 
     */
    public static function getStates()
    {
        return ExternalEntityConfig::getStatesFor('ShiftOrganization');
    }

    /**
     * Get states list
     *
     * @return array 
     */
    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('ShiftOrganization'));
    }

    /**
     * Set shift
     *
     * @param \App\Entity\Shift $shift
     *
     * @return ShiftOrganization
     */
    public function setShift(\App\Entity\Shift $shift)
    {
        $this->shift = $shift;

        return $this;
    }

    /**
     * Get shift
     *
     * @return \App\Entity\Shift
     */
    public function getShift()
    {
        return $this->shift;
    }

    /**
     * Set organization
     *
     * @param \App\Entity\Organization $organization
     *
     * @return ShiftOrganization
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

    public function __toString()
    {
        // TODO: Guess. (Yeah, translation).
        return $this->amount . " from " . (string)$this->organization;
    }
}
