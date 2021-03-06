<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use App\Lib\ExternalEntityConfig;

/**
 * @ORM\Entity
 * @ORM\Table(name="crewcall_shift")
 * @ORM\Entity(repositoryClass="App\Repository\ShiftRepository")
 * @Gedmo\Loggable
 */
class Shift
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="shifts")
     * @ORM\JoinColumn(name="event_id", referencedColumnName="id", nullable=false)
     */
    private $event;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="starttime", type="datetime", nullable=false)
     * @Gedmo\Versioned
     */
    private $start;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="endtime", type="datetime", nullable=false)
     * @Gedmo\Versioned
     */
    private $end;

    /**
     * @var string $state
     *
     * @ORM\Column(name="state", type="string", length=40, nullable=false)
     * @Gedmo\Versioned
     * @Assert\Choice(callback = "getStatesList")
     */
    private $state;

    /**
     * @ORM\ManyToOne(targetEntity="FunctionEntity", inversedBy="shifts")
     * @ORM\JoinColumn(name="function_id", referencedColumnName="id", nullable=false)
     */
    private $function;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="integer", nullable=false)
     * @Gedmo\Versioned
     */
    private $amount;

    /**
     * @ORM\OneToMany(targetEntity="Job", mappedBy="shift", cascade={"persist","remove"})
     */
    private $jobs;

    /**
     * @ORM\OneToMany(targetEntity="ShiftOrganization", mappedBy="shift", cascade={"remove"})
     */
    private $shift_organizations;

    public function __construct($options = array())
    {
        $this->jobs  = new ArrayCollection();
        $this->shift_organizations  = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Set start
     *
     * @param \DateTime $start
     *
     * @return Shift
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end
     *
     * @param \DateTime $end
     *
     * @return Shift
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
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
        return ExternalEntityConfig::getStatesFor('Shift')[$state]['label'];
    }

    /**
     * Get states
     *
     * @return array 
     */
    public static function getStates()
    {
        return ExternalEntityConfig::getStatesFor('Shift');
    }

    /**
     * Get states list
     *
     * @return array 
     */
    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('Shift'));
    }

    /**
     * Set event
     *
     * @param \App\Entity\Event $event
     *
     * @return Shift
     */
    public function setEvent(\App\Entity\Event $event)
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

    /**
     * Set function
     *
     * @param \App\Entity\FunctionEntity $function
     *
     * @return Shift
     */
    public function setFunction(\App\Entity\FunctionEntity $function)
    {
        $this->function = $function;

        return $this;
    }

    /**
     * Get function
     *
     * @return \App\Entity\FunctionEntity
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * Set amount
     *
     * @param integer $amount
     *
     * @return Shift
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
     * Add job
     *
     * @param \App\Entity\Job $job
     *
     * @return Shift
     */
    public function addJob(\App\Entity\Job $job)
    {
        if ($this->jobs->contains($job))
            return $this;
        $this->jobs[] = $job;
        $job->setShift($this);

        return $this;
    }

    /**
     * Remove job
     *
     * @param \App\Entity\Job $job
     */
    public function removeJob(\App\Entity\Job $job)
    {
        $this->jobs->removeElement($job);
    }

    /**
     * Get jobs
     * Annoyingly simple filter for now.
     * TODO: Use Criterias
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJobs($filter = [])
    {
        $jobs = new ArrayCollection();
        foreach ($this->jobs as $job) {
            if (isset($filter['states']) && !in_array($job->getState(), $filter['states']))
                continue;
            $jobs->add($job);
        }

        if (isset($filter['sort_by'])) {
            $iterator = $jobs->getIterator();
            $iterator->uasort(function ($a, $b) use ($filter) {
                if ($filter['sort_by'] == 'last_name') {
                    return strcasecmp($a->getPerson()->getLastName(), $b->getPerson()->getLastName());
                }
                if ($filter['sort_by'] == 'first_name') {
                    return strcasecmp($a->getPerson()->getFirstName(), $b->getPerson()->getFirstName());
                }
                if ($filter['sort_by'] == 'name') {
                    return strcasecmp($a->getPerson()->getName(), $b->getPerson()->getName());
                }
                if ($filter['sort_by'] == 'username') {
                    return strcasecmp($a->getPerson()->getUserName(), $b->getPerson()->getUserName());
                }
            });
            return new ArrayCollection(iterator_to_array($iterator));
        }

        return $jobs;
    }

    /**
     * Add shift_organizations
     *
     * @param \App\Entity\ShiftOrganization $shift_organizations
     *
     * @return Shift
     */
    public function addShiftOrganization(\App\Entity\ShiftOrganization $shift_organizations)
    {
        $this->shift_organizations[] = $shift_organizations;

        return $this;
    }

    /**
     * Remove shift_organizations
     *
     * @param \App\Entity\ShiftOrganization $shift_organizations
     */
    public function removeOrganization(\App\Entity\ShiftOrganization $shift_organizations)
    {
        $this->shift_organizations->removeElement($shift_organizations);
    }

    /**
     * Get shift_organizations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getShiftOrganizations()
    {
        return $this->shift_organizations;
    }

    public function __toString()
    {
        // This is just too little, but gotta look at it later and I guess
        // adding date/time is the correct thing to do. And maybe get rid of
        // the location.
        return $this->getFunction() . " at " . $this->getEvent();
    }

    /**
     * Count jobs and personorgs by state
     *
     * @return int
     */
    public function getJobsAmountByState($state = null)
    {
        $amounts = [
            'INTERESTED' => 0,
            'ASSIGNED' => 0,
            'CONFIRMED' => 0,
            ];
        foreach ($this->getJobs() as $job) {
            $s = $job->getState();
            if (!isset($amounts[$s]))
                $amounts[$s] = 0;
            $amounts[$s]++;
        }
        foreach ($this->getShiftOrganizations() as $so) {
            // If they are mentioned, they are booked. Aka amount is by
            // definition booked.
            $s = $so->getState();
            if (!isset($amounts[$s]))
                $amounts[$s] = 0;
            $amounts[$s] += $so->getAmount();
        }
        if ($state)
            return $amounts[$state] ?: 0;
        return $amounts;
    }

    /**
     * Get the amount of persons Booked, including organization
     *
     * @return int
     */
    public function getBookedAmount()
    {
        $booked = 0;
        foreach ($this->getJobs() as $j) {
            if ($j->isBooked()) $booked++;
        }
        foreach ($this->getShiftOrganizations() as $so) {
            // If they are mentioned, they are booked. Aka amount is by
            // definition booked.
            $booked += $so->getAmount();
        }
        return $booked;
    }

    /**
     * Get the amount of persons registered, including organization
     *
     * @return int
     */
    public function getRegisteredAmount()
    {
        $booked = $this->getJobs()->count();
        foreach ($this->getShiftOrganizations() as $so) {
            // If they are mentioned, they are booked. Aka amount is by
            // definition booked.
            $booked += $so->getAmount();
        }
        return $booked;
    }

    public function isOpen()
    {
        /*
         * It may be discussible to not let a shift be open unless the event
         * is. But setting the state "READY" will close all shifts and
         * therefore make them not open. Which may be too strict.
         * Admins may want to mark an event as ready while keeping the abolity
         * to register interest on the shift anyway. Which is why the event is
         * not checked here.
         */
        return in_array($this->getState(), ExternalEntityConfig::getOpenStatesFor('Shift'));
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context)
    {
        if ($this->end && ($this->start >= $this->end)) {
            $context->buildViolation('You can not set start time to after end time.')
                ->atPath('start')
                ->addViolation();
        }
    }
}
