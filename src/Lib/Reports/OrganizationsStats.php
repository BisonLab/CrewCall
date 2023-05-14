<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

use App\Entity\Event;

/*
 *
 */
class OrganizationsStats extends CommonReportFunctions
{
    protected $container;

    public function __construct($container, $options = array())
    {
        $this->container = $container;
    }

    // All fixed reports shall be hydrated as arrays.
    public function runFixedReport($config = null)
    {
        $em = $this->getManager();
        $eventrepo = $em->getRepository(Event::class);

        $header = [
            'Name',
            'Main events count',
            'Events count',
            'Shifts',
            'Jobs',
            'Unique crewmembers',
        ];

        if ($from_date = $config['from_date'] ?? null) {
            $options['from'] = $from_date;
        }

        if ($to_date = $config['to_date'] ?? null) {
            $options['to'] = $to_date;
        }

        $organizations = [];
        foreach($eventrepo->findEvents($options) as $event) {
            if ($event->getShifts()->count() == 0)
                continue;
            $organization = $event->getOrganization();
            // Collect it or create it.
            if (!$orgarr = $organizations[$organization->getName()] ?? false) {
                $orgarr = [
                    'main_events' => [],
                    'events' => [],
                    'shifts' => 0,
                    'jobs' => 0,
                    'people' => [],
                ];
            }
            $orgarr['main_events'][$event->getMainEvent()->getId()] ??= true;
            $orgarr['events'][$event->getId()] ??= true;
            foreach ($event->getShifts() as $shift) {
                $orgarr['shifts']++;
                foreach ($shift->getJobs() as $job) {
                    if (!$job->isBooked()) continue;
                    $orgarr['people'][$job->getPerson()->getId()] ??= true;
                    $orgarr['jobs']++;
                }
            }
            // Put it back
            $organizations[$organization->getName()] = $orgarr;
        }

        $data = array();
        foreach ($organizations as $name => $orgarr) {
            $data[] = [
                'Name' => $name,
                'Main events count' => count($orgarr['main_events']),
                'Events count' => count($orgarr['events']),
                'Shifts' => $orgarr['shifts'],
                'Jobs' => $orgarr['jobs'],
                'Unique crewmembers' => count($orgarr['people']),
            ];
        }

        return ['data' => $data, 'header' => $header];
    }
}
