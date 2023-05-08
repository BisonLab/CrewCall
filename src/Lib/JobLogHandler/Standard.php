<?php

namespace App\Lib\JobLogHandler;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Person;
use App\Entity\Shift;

class Standard
{
    private $em;
    private $params;

    public function __construct($em, $params)
    {
        $this->em = $em;
        $this->params = $params;
    }

    public function getJobLogsForPerson(Person $person, $options = array())
    {
        /*
         * This has to do a lot more. It should use criterias to narrow down
         * the amount of joblog entries. Maybe even do this with a join!
         * Right now I'll just return the summary and all joblogs there is.
         */
        $summary = array(
            'week'      => ['jobs' => 0, 'minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'l7days'    => ['jobs' => 0, 'minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'month'     => ['jobs' => 0, 'minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'year'      => ['jobs' => 0, 'minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'last_year' => ['jobs' => 0, 'minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'total'     => ['jobs' => 0, 'minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
        );

        $full_day_minutes = $this->params->get('joblogs_handler')['full_day_minutes'];
        $half_day_minutes = $full_day_minutes / 2;;

        /*
         * strtotime is not to be trusted and "first day of " will only work
         * on month and nothing else.
         * Alas, this is a pretty diverse piece of code.
         */
        $first_of_week = new \DateTime('00:00');
        $first_of_week->modify('this week');
        $l7days = new \DateTime('00:00');
        $l7days->modify('-7 days');
        $first_of_month     = new \DateTime();
        $first_of_month->modify('first day of this month');
        $first_of_year      = new \DateTime(date('Y-01-01'));
        $first_of_last_year = new \DateTime(date('Y-01-01'));
        $first_of_last_year->modify('-1 year');
        $joblogs = new \Doctrine\Common\Collections\ArrayCollection();
        $joblog_array = [];
        foreach ($person->getJobs() as $job) {
            $counted_job = false;
            foreach ($job->getJobLogs() as $jl) {
                // TODO: Check state. I guess "COMPLETED" is the one to use.
                $joblogs->add($jl);
                $in  = $jl->getIn();
                $out = $jl->getOut();
                $minutes = $jl->getWorkedMinutes();
                $joblog_array[] = [
                    'in' => $in->format("Y-m-d H:i"),
                    'out' => $out->format("Y-m-d H:i"),
                    'job' => (string)$jl->getShift()
                ];
                // DateTime interval does NOT work. Stupidly enough.
                $summary['total']['minutes'] += $minutes;

                if ($out < $first_of_year && $out > $first_of_last_year) {
                    $summary['last_year']['minutes'] += $minutes;
                    if (!$counted_job)
                        $summary['last_year']['jobs']++;
                    if ($minutes > $half_day_minutes)
                        $summary['last_year']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['last_year']['half']++;
                }
                if ($out > $first_of_week) {
                    $summary['week']['minutes'] += $minutes;
                    if (!$counted_job)
                        $summary['week']['jobs']++;
                    if ($minutes > $half_day_minutes)
                        $summary['week']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['week']['half']++;
                }
                if ($out > $l7days) {
                    if (!$counted_job)
                        $summary['l7days']['jobs']++;
                    $summary['l7days']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['l7days']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['l7days']['half']++;
                }
                if ($out > $first_of_month) {
                    if (!$counted_job)
                        $summary['month']['jobs']++;
                    $summary['month']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['month']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['month']['half']++;
                }
                if ($out > $first_of_year) {
                    if (!$counted_job)
                        $summary['year']['jobs']++;
                    $summary['year']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['year']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['year']['half']++;
                }
                if ($minutes > 0)
                    $counted_job = true;
            }
        }
        $summary['week']['hours']      = $this->mToHm($summary['week']['minutes']);
        $summary['l7days']['hours']    = $this->mToHm($summary['l7days']['minutes']);
        $summary['month']['hours']     = $this->mToHm($summary['month']['minutes']);
        $summary['year']['hours']      = $this->mToHm($summary['year']['minutes']);
        $summary['last_year']['hours'] = $this->mToHm($summary['last_year']['minutes']);
        $summary['total_hours']['hours']     = $this->mToHm($summary['total']['minutes']);

        // Somehow it's not sorted.
        $iterator = $joblogs->getIterator();
        $iterator->uasort(function ($a, $b) {
            if ($a->getIn()->format("U") == $b->getIn()->format("U")) return 0;
            return ($a->getIn()->format("U") > $b->getIn()->format("U")) ? -1 : 1;
        });

        return [
            'joblog_array' => $joblog_array,
            'joblogs' => iterator_to_array($iterator),
            'summary' => $summary
            ];
    }
    
    private function mToHm($minutes)
    {
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);
    }
}
