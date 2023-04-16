<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use DateTime;

use App\Lib\ExternalEntityConfig;

/**
 * JobLog - In and out of work.
 *
 * @ORM\Table(name="crewcall_joblog")
 * @ORM\Entity(repositoryClass="App\Repository\JobLogRepository")
 * @Gedmo\Loggable
 */
class JobLog
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="intime", type="datetime", nullable=false)
     * @Gedmo\Versioned
     */
    private $in;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="outtime", type="datetime", nullable=true)
     * @Gedmo\Versioned
     */
    private $out;

    /**
     * @var integer
     *
     * @ORM\Column(name="break_minutes", type="integer", nullable=true, options={"default" = "0"})
     * @Gedmo\Versioned
     */
    private $break_minutes = 0;

    /**
     * @var string $state
     *
     * @ORM\Column(name="state", type="string", length=40, nullable=true)
     * @Gedmo\Versioned
     * @Assert\Choice(callback = "getStatesList")
     */
    private $state;

    /**
     * @var array
     *
     * @ORM\Column(name="attributes", type="json")
     */
    private $attributes = array();

    /**
     * @ORM\ManyToOne(targetEntity="Job", inversedBy="joblogs")
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id", nullable=false)
     */
    private $job;

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
     * Set state
     *
     * @param string $state
     * @return JobLog
     */
    public function setState($state)
    {
        if ($state == $this->state) return $this;
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
        return ExternalEntityConfig::getStatesFor('JobLog')[$state]['label'];
    }

    /**
     * Get states
     *
     * @return array 
     */
    public static function getStates()
    {
        return ExternalEntityConfig::getStatesFor('JobLog');
    }

    /**
     * Get states list
     *
     * @return array 
     */
    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('JobLog'));
    }

    /**
     * Set attributes
     *
     * @param array $attributes
     *
     * @return JobLog
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get in
     *
     * @return \DateTime
     */
    public function getIn(): ?\DateTime
    {
        return $this->in;
    }

    /**
     * set in
     *
     * @param \datetime $in
     *
     * @return joblog
     */
    public function setIn(?\DateTime $in)
    {
        $this->in = $in;

        return $this;
    }

    /*
     * This is the "string" version, with hours and minutes.
     * What this does not is remove a day if your shift starts a 01:00
     * but you started working 23:00 - I have to find a smart'ish algorithm for
     * it.
     */
    public function setInTime(string $inTime)
    {
        // Gives us a correct date.
        $in = clone($this->getJob()->getStart());
        if (preg_match("/\D/", $inTime)) {
            list($ih, $im) = preg_split("/\D/", $inTime);
        } else {
            $ih = $times['inn1'];
            $im = 0;
        }
        $in->setTime($ih, $im);
         
        // I'll try, I am certain this may fail in some cases.
        // (Yes, if you work > 24 hours aswell.)
        $jobout = $this->getJob()->getEnd();
        if ($in > $jobout)
            $in->modify("-1 day");

        $this->in = $in;

        return $this;
    }

    /**
     * Get out
     *
     * @return \DateTime
     */
    public function getOut(): ?\DateTime
    {
        return $this->out;
    }

    /**
     * Set out
     *
     * @param \datetime $out
     *
     * @return joblog
     */
    public function setOut(?\DateTime $out)
    {
        $this->out = $out;

        return $this;
    }

    /*
     * Better set In before this!
     * And as with setIn, but the opposite, add a day if out time is before in
     * time.
     * It *will* break if a shift or job is beyond 24 hours, probably less.
     */
    public function setOutTime(string $outTime):self
    {
        $out = clone($this->getJob()->getStart());
        if (preg_match("/\D/", $outTime)) {
            list($uh, $um) = preg_split("/\D/", $outTime);
        } else {
            $uh = $outTime;
            $um = 0;
        }
        $out->setTime($uh, $um);
        if ($this->getIn() > $out)
            $out->modify("+1 day");

        $this->out = $out;

        return $this;
    }

    /**
     * Get break minutes
     *
     * @return \DateTime
     */
    public function getBreakMinutes()
    {
        return $this->break_minutes;
    }

    /**
     * Set out
     *
     * @param \datetime $out
     *
     * @return joblog
     */
    public function setBreakMinutes($break_minutes)
    {
        $this->break_minutes = $break_minutes;

        return $this;
    }

    public function getPerson()
    {
        return $this->getJob()->getPerson();
    }

    public function getShift()
    {
        // Do not expect there to be something when it's created.
        if ($this->job)
            return $this->getJob()->getShift();
        return null;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setJob(Job $job = null)
    {
        if ($this->job !== null) {
            $this->job->removeJobLog($this);
        }

        if ($job !== null) {
            $job->addJobLog($this);
        }

        $this->job = $job;
        return $this;
    }

    public function getWorkedMinutes()
    {
        return (($this->getOut()->getTimeStamp() - $this->getIn()->getTimeStamp()) / 60) - $this->getBreakMinutes();
    }

    public function getWorkedTime()
    {
        $minutes = $this->getWorkedMinutes();
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);
    }
}
