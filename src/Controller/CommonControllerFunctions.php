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
        $em = $this->getDoctrine()->getManager();
        $job_repo = $em->getRepository(Job::class);

        $select_grouping = $options['select_grouping'] ?? null;
        $crew_only = $options['crew_only'] ?? false;
        $on_date = $options['on_date'] ?? null;

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
            $jobs = $job_repo->findJobs([
                    'from' => $on_date,
                    'to' => $on_date,
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
                }

                if ($select_grouping == 'all_crewmembers') {
                    if (!$filtered->contains($p))
                        $filtered->add($p);
                }

                if ($select_grouping == "available") {
                    if ($p->isOccupied(['date' => $on_date]))
                        continue;
                    if (!$filtered->contains($p))
                        $filtered->add($p);
                    continue;
                }
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
