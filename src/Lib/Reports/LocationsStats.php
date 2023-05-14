<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

use App\Entity\Event;

/*
 *
 */
class LocationsStats extends CommonReportFunctions
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

        $locations = [];
        foreach($eventrepo->findEvents($options) as $event) {
            if ($event->getShifts()->count() == 0)
                continue;
            $location = $event->getLocation()->getMainLocation();
            // Collect it or create it.
            if (!$locarr = $locations[$location->getName()] ?? false) {
                $locarr = [
                    'main_events' => [],
                    'events' => [],
                    'shifts' => 0,
                    'jobs' => 0,
                    'people' => [],
                ];
            }
            $locarr['main_events'][$event->getMainEvent()->getId()] ??= true;
            $locarr['events'][$event->getId()] ??= true;
            foreach ($event->getShifts() as $shift) {
                $locarr['shifts']++;
                foreach ($shift->getJobs() as $job) {
                    if (!$job->isBooked()) continue;
                    $locarr['people'][$job->getPerson()->getId()] ??= true;
                    $locarr['jobs']++;
                }
            }
            // Put it back
            $locations[$location->getName()] = $locarr;
        }

        $data = array();
        foreach ($locations as $name => $locarr) {
            $data[] = [
                'Name' => $name,
                'Main events count' => count($locarr['main_events']),
                'Events count' => count($locarr['events']),
                'Shifts' => $locarr['shifts'],
                'Jobs' => $locarr['jobs'],
                'Unique crewmembers' => count($locarr['people']),
            ];
        }

        return ['data' => $data, 'header' => $header];
    }
}
