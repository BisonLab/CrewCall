<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

use App\Entity\Event;

/*
 *
 */
class FunctionsStats implements ReportsInterface
{
    use \BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

    public function getReportName(): string
    {
        return "FunctionsStats";
    }

    public function getDescription(): string
    {
        return "Summary of jobs, events and people per Function.";
    }

    public function getCriterias(): array
    {
        return ['function'];
    }

    public function getRequiredOptions(): array
    {
        return ['function'];
    }

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
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

        $options = [];
        if ($from_date = $config['from_date'] ?? null) {
            $options['from'] = $from_date;
        }

        if ($to_date = $config['to_date'] ?? null) {
            $options['to'] = $to_date;
        }

        $functions = [];
        foreach($eventrepo->findEvents($options) as $event) {
            if ($event->getShifts()->count() == 0)
                continue;
            foreach ($event->getShifts() as $shift) {
                $function = $shift->getFunction();
                // Collect it or create it.
                if (!$funcarr = $functions[$function->getName()] ?? false) {
                    $funcarr = [
                        'main_events' => [],
                        'events' => [],
                        'shifts' => 0,
                        'jobs' => 0,
                        'people' => [],
                    ];
                }
                $funcarr['main_events'][$event->getMainEvent()->getId()] ??= true;
                $funcarr['events'][$event->getId()] ??= true;
                $funcarr['shifts']++;
                foreach ($shift->getJobs() as $job) {
                    if (!$job->isBooked()) continue;
                    $funcarr['people'][$job->getPerson()->getId()] ??= true;
                    $funcarr['jobs']++;
                }
                // Put it back
                $functions[$function->getName()] = $funcarr;
            }
        }

        $data = array();
        foreach ($functions as $name => $funcarr) {
            $data[] = [
                'Name' => $name,
                'Main events count' => count($funcarr['main_events']),
                'Events count' => count($funcarr['events']),
                'Shifts' => $funcarr['shifts'],
                'Jobs' => $funcarr['jobs'],
                'Unique crewmembers' => count($funcarr['people']),
            ];
        }

        return ['data' => $data, 'header' => $header];
    }
}
