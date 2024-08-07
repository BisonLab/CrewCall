<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

/*
 */

class WorkLog implements ReportsInterface
{
    use \BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

    public function getReportName(): string
    {
        return "WorkLog";
    }

    public function getDescription(): string
    {
        return "Jobs done in an event.";
    }

    public function getCriterias(): array
    {
        return ['event'];
    }

    public function getRequiredOptions(): array
    {
        return ['event'];
    }

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    // All fixed reports shall be hydrated as arrays.
    public function runFixedReport($config = null)
    {
        if (!$event = $config['event'] ?? null)
            throw new \InvalidArgumentException("You need to pick an event.");

        $em = $this->getManager();
        $jobservice = $this->container->get('crewcall.jobs');
        $jobloghandler = $this->container->get('crewcall.joblogs');

        $qb = $em->createQueryBuilder();
        $qb->select('e')
           ->from('App\Entity\Event', 'e')
           ->where('e.id = :event')
           ->setParameter('event', $config['event'])
        ;

        $header = [
            'Event name',
            'Function',
            'Shift Start',
            'Shift End',
            'Name',
            'Time worked, minutes',
            'Time worked, hours',
            'First in',
            'Last out',
        ];

        /*
         * Feel free to define this as lazyness.
         */
        $result = $qb->getQuery()->iterate();
        // This one can be really big..
        $data = array();
        foreach ($result as $itemres) {
            $event = $itemres[0];
            foreach ($event->getAllShifts() as $shift) {
                foreach ($shift->getJobs() as $job) {
                    $arr = [];
                    $arr[] = $event->getName(); 
                    $arr[] = (string)$shift->getFunction(); 
                    $arr[] = $shift->getStart()->format("Y-m-d H:i"); 
                    $arr[] = $shift->getEnd()->format("H:i"); 
                    $arr[] = $job->getPerson()->getFullName();
                    $minutes = 0;
                    $first = null;
                    $last = null;
                    foreach ($job->getJobLogs() as $joblog) {
                        if (!$first) $first = $joblog->getIn();
                        $minutes += ($joblog->getOut()->getTimeStamp() - $joblog->getIn()->getTimeStamp()) / 60;
                        $last = $joblog->getOut();
                    }
                    if ($minutes > 0) {
                        $arr[] = $minutes;
                        $h = floor($minutes / 60);
                        $m = $minutes % 60;
                        $arr[] = $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);
                        $arr[] = $first->format("Y-m-d H:i");
                        $arr[] = $last->format("Y-m-d H:i");
                        $data[] = $arr;
                    }
                }
            }
        }

        return ['data' => $data, 'header' => $header];
    }
}
