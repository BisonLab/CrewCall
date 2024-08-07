<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

use App\Lib\ExternalEntityConfig;

/**
 * Job
 *
 */
#[ORM\Entity(repositoryClass: \App\Repository\JobRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'crewcall_job')]
#[ORM\Index(name: 'crewcall_job_state_idx', columns: ['state'])]
#[ORM\UniqueConstraint(name: 'person_shift_job_idx', columns: ['person_id', 'shift_id'])]
#[Gedmo\Loggable]
class Job
{
    use NotesTrait;
    use \BisonLab\CommonBundle\Entity\AttributesTrait;

    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var string $state
     *
     */
    #[ORM\Column(name: 'state', type: 'string', length: 40, nullable: true)]
    #[Assert\Choice(callback: 'getStatesList')]
    #[Gedmo\Versioned]
    private $state;

    /**
     * @var string $ucode
     * Just a unique representation of the ID.
     *
     */
    #[ORM\Column(name: 'ucode', type: 'string', length: 10, unique: true, nullable: false)]
    #[Gedmo\Versioned]
    private $ucode;

    #[ORM\ManyToOne(targetEntity: \Person::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: false)]
    private $person;

    #[ORM\ManyToOne(targetEntity: \Shift::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(name: 'shift_id', referencedColumnName: 'id', nullable: false)]
    private $shift;

    #[ORM\OneToMany(targetEntity: \JobLog::class, mappedBy: 'job', cascade: ['remove', 'persist'])]
    #[ORM\OrderBy(['in' => 'ASC'])]
    private $joblogs;

    #[ORM\Column(name: 'state_changed', type: 'datetime', nullable: true)] // This is a lot quicker to pull than the info from Gedbo Loggable.
    private $state_changed;

    /*
     * Another placeholder. Boolean to hint that this job overlaps with
     * another. Will be used in twig templates, but may have other uses
     * aswell.
     * But you have to use "checkOverlap" in the crewcall.jobs service for
     * this to be set.
     */
    private $overlap;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get uCode
     *
     * @return string
     */
    public function getUcode()
    {
        return $this->ucode;
    }

    /**
     * Set uCode
     *
     * @return string
     */
    #[ORM\PrePersist]
    public function setUcode()
    {
        /*
         * I don't want consecutive codes alas I can't just use the ID.
         * I hope this does the trick. It is a remote possibility that the
         * combination of two IDs ends up with the same key when one of the
         * two parts exeed the minimum three chars.
         *
         * (Edit: It has happened. inserted 80K jobs and woopsie. Added time()
         *
         * And BTW, Usinng this (Job) ID does not ork since there is none in
         * prePersist and I am too lazy hacking around anything to add the ID
         * before it's inserted, which will (unique) fail.
         * Now we have a very harsh chek for double bookings, shifts not time.
         * TODO: Add such checks so that it'll never throw an exception.
         */
        $p1 = strrev(\ShortCode\Reversible::convert(
                $this->getShift()->getId(),
                    \ShortCode\Code::FORMAT_ALNUM_SMALL, 2));

        $p2 = strtolower(\ShortCode\Random::get(2));

        $p3 = \ShortCode\Reversible::convert(
                $this->getPerson()->getId(),
                    \ShortCode\Code::FORMAT_ALNUM_SMALL, 2);

        $this->ucode = $p1 . $p2 . $p3;

        return $this->ucode;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return Job
     */
    public function setState($state)
    {
        if ($state == $this->state) return $this;
        $state = strtoupper($state);
        if (!isset(self::getStates()[$state])) {
            throw new \InvalidArgumentException(sprintf('The "%s" state is not a valid state.', $state));
        }

        $this->setStateChanged();
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
        return ExternalEntityConfig::getStatesFor('Job')[$state]['label'];
    }

    /**
     * Get state order
     *
     * @return string 
     */
    public function getStateOrder($state = null)
    {
        $state = $state ?: $this->getState();
        return ExternalEntityConfig::getStatesFor('Job')[$state]['order'];
    }

    /**
     * Get states
     *
     * @return array 
     */
    public static function getStates()
    {
        return ExternalEntityConfig::getStatesFor('Job');
    }

    /**
     * Get states list
     *
     * @return array 
     */
    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('Job'));
    }

    public function getPerson()
    {
        return $this->person;
    }

    public function setPerson(Person $person = null)
    {
        if ($this->person !== null) {
            $this->person->removeJob($this);
        }

        if ($person !== null) {
            $person->addJob($this);
        }

        $this->person = $person;
        return $this;
    }

    public function getShift()
    {
        return $this->shift;
    }

    public function setShift(Shift $shift = null)
    {
        if ($this->shift !== null) {
            $this->shift->removeJob($this);
        }

        if ($shift !== null) {
            $shift->addJob($this);
        }

        $this->shift = $shift;
        return $this;
    }

    /**
     * Add joblog
     *
     * @param \App\Entity\JobLog $joblog
     *
     * @return Shift
     */
    public function addJobLog(\App\Entity\JobLog $joblog)
    {
        $this->joblogs[] = $joblog;

        return $this;
    }

    /**
     * Remove job
     *
     * @param \App\Entity\JobLog $joblog
     */
    public function removeJobLog(\App\Entity\JobLog $joblog)
    {
        $this->joblogs->removeElement($joblog);
    }

    /**
     * Get joblogs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJobLogs()
    {
        return $this->joblogs;
    }

    public function getStateChanged()
    {
        return $this->state_changed;
    }

    public function setStateChanged()
    {
        $this->state_changed = new \DateTime();
        return $this;
    }

    /*
     * Access functions
     */
    public function getFunction()
    {
        return $this->getShift()->getFunction();
    }

    public function getEvent()
    {
        return $this->getShift()->getEvent();
    }

    public function getLocation()
    {
        return $this->getShift()->getLocation();
    }

    public function getStart()
    {
        return $this->getShift()->getStart();
    }

    public function getEnd()
    {
        return $this->getShift()->getEnd();
    }

    public function isBooked()
    {
        return in_array($this->getState(), ExternalEntityConfig::getBookedStatesFor('Job'));
    }

    /*
     * This is simple. You'd say I should check if this is way after the job
     * should have been concluded. But it will show an open joblog, which is
     * the main point here. A joblog that should be closed.
     */
    public function isWorking()
    {
        foreach ($this->getJobLogs() as $jl) {
            if ($jl->getIn() && !$jl->getOut())
                return true;
        }
        return false;
    }

    public function isNoShow()
    {
        return in_array($this->getState(), ExternalEntityConfig::getNoShowStatesFor('Job'));
    }

    public function __toString()
    {
        return (string)$this->getFunction();
    }

    public function getOverlap()
    {
        return $this->overlap;
    }

    public function setOverlap($overlap)
    {
        $this->overlap = $overlap;
        return $this;
    }

    public function getWorkedMinutes()
    {
        $mins = 0;
        foreach ($this->getJobLogs() as $jl) {
            $mins += $jl->getWorkedMinutes();
        }
        return $mins;
    }

    public function getWorkedTime()
    {
        $minutes = $this->getWorkedMinutes();
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);
    }

    public function getBreakMinutes()
    {
        $mins = 0;
        foreach ($this->getJobLogs() as $jl) {
            $mins += $jl->getBreakMinutes();
        }
        return $mins;
    }
}
