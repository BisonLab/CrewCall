<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Job;
use App\Entity\Person;
use App\Entity\Shift;
use App\Entity\Event;
use App\Lib\ExternalEntityConfig;

class Jobs
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
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
     * Crew manager functions. (When we have a crew chief)
     */

    /*
     * Person specific functions.
     */

    public function jobsForPerson(Person $person, $options = array())
    {
        $jobs = $this->entityManager->getRepository(Job::class)
            ->findJobsForPerson($person, $options);

        if ($options['no_overlap_filter'] ?? false)
            return $jobs;
        $c = $this->checkOverlap($jobs);
        return $c;
    }

    public function opportunitiesForPerson(Person $person, $options = array())
    {
        // Should I cache or should I not?
        // Hopefully Doctrine does the job just as good, so I won't for now.
        $opportunities = new ArrayCollection();
        $jobshift = new ArrayCollection();
        $options['states'] = array_keys(ExternalEntityConfig::getStatesFor('Job'));
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
        $shifts = $this->entityManager->getRepository(Shift::class)
            ->findUpcomingForFunctions($functions, $options);

        foreach ($shifts as $sf) {
            // If not open for registration, don't.
            if (!$sf->isOpen())
                continue;
            // Already in jobs? And not asking for all of it anyway.
            if ($options['include_signed_up'] ?? false || !$jobshift->contains($sf)) {
                // Check if we have time overlap between already booked job and
                // the opportunities.
                /*
                 * Gotta decide if I want to do this or not. Not for now since
                 * it ends up removing opportunities also when the existing job
                 * is just in the wishlist.
                foreach ($jobshift as $jsf) {
                    if ($this->shiftOverlaps($jsf->getShift(), $sf->getShift()))
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
     * And this one sets the Overlap flag if there is an ovelap at all.
     */
    public function checkOverlap($jobs)
    {
        $last = null;
        $checked = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($jobs as $job) {
            if ($last && $this->shiftOverlaps($job->getShift(), $last->getShift())) {
                $job->setOverlap(true);
                $last->setOverlap(true);
            }
            $checked->add($job);
            $last = $job;
        }
        return $checked;
    }

    public function shiftOverlaps(Shift $one, Shift $two)
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
        $job_repo = $this->entityManager->getRepository(Job::class);
        return $job_repo->checkOverlapForPerson($job, $options);
    }
}
