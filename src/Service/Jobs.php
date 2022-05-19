<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Job;
use App\Entity\Person;
use App\Entity\Shift;
use App\Entity\Event;

class Jobs
{
    private $em;
    private $sakonnin;

    public function __construct($em, $sakonnin)
    {
        $this->em = $em;
        $this->sakonnin = $sakonnin;
    }

    /*
     * Kinda not related to this, but kinda is aswell - functions.
     */

    /*
     * Gotta have control on checks to check against.
     */
    public function checksForShift(Shift $shift)
    {
        $inform_checks = $shift->getNotesByType('InformCheck');
        $confirm_checks = $shift->getNotesByType('ConfirmCheck');

        $event = $shift->getEvent();
        $inform_checks = array_merge($inform_checks,
            $event->getNotesByType('InformCheck'));
        $confirm_checks = array_merge($confirm_checks,
            $event->getNotesByType('ConfirmCheck'));
        if ($epar = $event->getParent()) {
                $inform_checks = array_merge($inform_checks,
                    $epar->getNotesByType('InformCheck'));
                $confirm_checks = array_merge($confirm_checks,
                    $epar->getNotesByType('ConfirmCheck'));
        }
        return array_merge($inform_checks, $confirm_checks);
    }

    /*
     * Admin functions.
     */

    /*
     * Crew chief functions. (When we have a crew chief)
     */

    /*
     * Person specific functions.
     */

    public function jobsForPerson(Person $person, $options = array())
    {
        $jobs = $this->em->getRepository(Job::class)
            ->findJobsForPerson($person, $options);
        $c = $this->checkOverlap($jobs);
        return $c;
    }

    public function opportunitiesForPerson(Person $person, $options = array())
    {
        // Should I cache or should I not?
        // Hopefully Doctrine does the job just as good, so I won't for now.
        $opportunities = new ArrayCollection();
        $jobshift = new ArrayCollection();
        $jobs = $this->jobsForPerson($person, $options);
        foreach ($jobs as $job) {
            $jobshift->add($job->getShift());
        }

        // I'd better have a "getFunctions" on Person, but I don't like
        // that name, so I'll wait until I've found one I like.
        $functions = array();
        foreach ($person->getPersonFunctions() as $pf) {
            $functions[] = $pf->getFunction();
        }
        $options['open'] = true;
        $shifts = $this->em->getRepository(Shift::class)
            ->findUpcomingForFunctions($functions, $options);

        foreach ($shifts as $sf) {
            // If not open for registration, don't.
            if (!$sf->isOpen())
                continue;
            // Already in jobs?
            if (!$jobshift->contains($sf)) {
                // Check if we have time overlap between already booked job and
                // the opportunities.
                /*
                 * Gotta decide if I want to do this or not. Not for now since
                 * it ends up removing opportunities also when the existing job
                 * is just in the wishlist.
                foreach ($jobshift as $jsf) {
                    if ($this->overlap($jsf->getShift(), $sf->getShift()))
                        continue 2;
                }
                 */
                // And it's still here.
                $opportunities->add($sf);
            }
        }
        return $opportunities;
    }

    /*
     * This sets a flag "Overlap" on every job that overlaps with a booked job
     * the same day. This is a compromise and kinda makes sense aswell.
     */
    public function checkOverlapByDay($jobs)
    {
        $last = null;
        $checked = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($jobs as $job) {
            if ($last && $this->overlap($job->getShift(), $last->getShift())) {
                $job->setOverlap(true);
                $last->setOverlap(true);
            }
            $checked->add($job);
            $last = $job;
        }
        return $checked;
    }

    /*
     * And this one sets the Overlap flag if there is an ovelap at all.
     */
    public function checkOverlap($jobs)
    {
        $last = null;
        $checked = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($jobs as $job) {
            if ($last && $this->overlap($job->getShift(), $last->getShift())) {
                $job->setOverlap(true);
                $last->setOverlap(true);
            }
            $checked->add($job);
            $last = $job;
        }
        return $checked;
    }

    public function overlap(Shift $one, Shift $two)
    {
        // Why bother checking if it's the same? :=)
        if ($one === $two) return true;
        return (($one->getStart() <= $two->getEnd()) && ($one->getEnd() >= $two->getStart()));
    }

    /*
     * Annoying name, just couldn't come up with a better.
     */
    public function checkOverlapForPerson(Job $job, $options = array())
    {
        $job_repo = $this->em->getRepository(Job::class);
        return $job_repo->checkOverlapForPerson($job, $options);
    }
}
