<?php
namespace App\Controller;

use App\Lib\ExternalEntityConfig;
use App\Entity\Job;

trait CommonControllerFunctions
{
    /*
     * In case of more filters, send request.
     * Yes, this is horrendously slow.
     */
    public function filterPeople($people, $options)
    {
        $job_repo = $this->entityManager->getRepository(Job::class);

        $select_grouping = $options['select_grouping'] ?? null;
        $crew_only = $options['crew_only'] ?? false;
        $on_date = $options['on_date'] ?? null;
        if (!$on_datetime = ($options['on_datetime'] ?? null)) {
            if ($on_date && $options['on_time']) {
                $on_datetime = new \DateTime($on_date);
                list($oh, $om) = preg_split("/\D/", $options['on_time']);
                $on_datetime->setTime($oh, $om);
                $on_datetime->setTime($oh, $om);
            }
        }

        // If all, return all.
        if (!$crew_only && $select_grouping == 'all') {
            return $people;
        }

        $filtered = new \Doctrine\Common\Collections\ArrayCollection();
        $jobs = null;
        if ($on_date) {
            $states = [];
            switch($select_grouping) {
                case 'booked':
                    $states = ExternalEntityConfig::getBookedStatesFor('Job');
                    break;
                case 'interested':
                    $states = ["INTERESTED"];
                    break;
                case 'assigned':
                    $states = ["ASSIGNED"];
                    break;
                case 'confirmed':
                    $states = ["CONFIRMED"];
                    break;
            }
            $from = new \DateTime($on_date);
            $to = new \DateTime($on_date);
            // Override.
            if ($on_datetime) {
                $from = clone($on_datetime);
                $to = clone($on_datetime);
            }
            $jobs = $job_repo->findJobs([
                    'from' => $from,
                    'to' => $to,
                    'states' => $states,
                    ]);
        }
        foreach ($people as $p) {
            if ($filtered->contains($p)) {
                continue;
            }
            if ($crew_only && !$p->isCrew()) {
                continue;
            }
            if ($select_grouping == "no_crew" && $p->isCrew()) {
                continue;
            }
            if ($select_grouping == "all") {
                if (!$filtered->contains($p))
                    $filtered->add($p);
            }
            if ($on_date) {
                if ($select_grouping == 'all_active') {
                    if ($p->getStateOnDate($on_date) != "ACTIVE")
                        continue;
                    if (!$filtered->contains($p))
                        $filtered->add($p);
                    // I did not have this one before but see no reason.
                    // I'll comment here in case there was.
                    continue;
                }

                if ($select_grouping == 'all_crewmembers') {
                    if (!$filtered->contains($p))
                        $filtered->add($p);
                    // I did not have this one before but see no reason.
                    // I'll comment here in case there was.
                    continue;
                }

                if ($select_grouping == "available") {
                    if ($on_datetime) {
                        if ($p->isOccupied(['datetime' => $on_datetime]))
                            continue;
                    } elseif ($p->isOccupied(['date' => $on_date])) {
                        continue;
                    }
                    if (!$filtered->contains($p))
                        $filtered->add($p);
                    continue;
                }
                // Any job at all? You may ask why this is here.
                // I am not entirely certain any more.
                foreach ($jobs as $j) {
                    if ($j->getPerson() == $p) {
                        if (!$filtered->contains($p))
                            $filtered->add($p);
                        break;
                    }
                }
            // And if no on_date set:
            } else {
                if ($select_grouping == 'all_active') {
                    if ($p->getState() != "ACTIVE")
                        continue;
                }
                if ($select_grouping == 'all_crewmembers') {
                    if (!in_array($p->getState(),
                            ExternalEntityConfig::getActiveStatesFor('Person')))
                        continue;
                }
                if (!$filtered->contains($p))
                    $filtered->add($p);
            }
        }
        return $filtered;
    }
}
