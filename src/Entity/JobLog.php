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
    public function setAttributes(?array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes(): mixed
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
     *
     * WHen the Out time is set we'll try to set the dates.
     */
    public function setInTime(string $inTime)
    {
        $job = $this->getJob();
        // Gives us a correct date.
        $in = clone($job->getStart());
        if (preg_match("/\D/", $inTime)) {
            list($in_hour, $in_minutes) = preg_split("/\D/", $inTime);
        } else {
            // I'll guess this is just the hours then. Maybe a bad idea, but
            // I have commented it, alas documented.
            $in_hour = $inTime;
            $in_minutes = 0;
        }
        if ($in_hour > 23 || $in_hour < 0)
            throw new \InvalidArgumentException("Bad hours value.");
        if ($in_minutes > 59 || $in_hour < 0)
            throw new \InvalidArgumentException("Bad minutes value.");

        // And set the base.
        $in->setTime($in_hour, $in_minutes);

        // But we might also have an issue where both in and out is the day
        // after the shifts start.
        $end_tomorrow = $job->getEnd()->format('Ymd') - $job->getStart()->format('Ymd') == 1;

        if ($end_tomorrow && $in < $job->getStart()) {
            $shiftout_hour = $job->getEnd()->format('H');
            if ($in->format('H') < $shiftout_hour)
                $in = $in->modify("+1 day");
        } elseif (!$end_tomorrow && $in > $job->getEnd()) {
            $in = $in->modify("-1 day");
        }

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
     * It *will* break if a shift or job is beyond 24 hours, probably less.
     *
     * It'll try to guess the dates based on the time entered.
     * (This is how TDD looks.)
     */
    public function setOutTime(string $outTime):self
    {
        $job = $this->getJob();
        if (!$in = $this->getIn())
            throw new \InvalidArgumentException("Need to set in time before out time.");
        // Yes, the start of the shift.
        $out = clone($job->getStart());
        if (preg_match("/\D/", $outTime)) {
            list($out_hour, $out_minutes) = preg_split("/\D/", $outTime);
        } else {
            $out_hour = $outTime;
            $out_minutes = 0;
        }
        if ($out_hour > 23 || $out_hour < 0)
            throw new \InvalidArgumentException("Bad hours value.");
        if ($out_minutes > 59 || $out_hour < 0)
            throw new \InvalidArgumentException("Bad moututes value.");

        // And date manipulation.
        $out->setTime($out_hour, $out_minutes);

        // First, if out is "before" in, it's probably the day after.
        if ($out < $in) {
            $out = $out->modify("+1 day");
        }

        // But we might also have an issue where both in and out is the day
        // after the shifts start.
        $end_tomorrow = $job->getEnd()->format('Ymd') - $job->getStart()->format('Ymd') == 1;

        if ($end_tomorrow && $out < $job->getStart()) {
            $shiftout_hour = $job->getEnd()->format('H');
            $shiftin_hour = $job->getStart()->format('H');
            $out = $out->modify("+1 day");
        }

        $this->out = $out;

        return $this;
    }

    /**
     * Get break minutes
     *
     * @return \DateTime
     */
    public function getBreakMinutes(): ?int
    {
        return $this->break_minutes;
    }

    /**
     * Set break in minutes
     *
     * @param \datetime $out
     *
     * @return joblog
     */
    public function setBreakMinutes(?int $break_minutes): self
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
