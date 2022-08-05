<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Person;
use App\Entity\Shift;

class JobLogs
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
            'week'      => ['minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'l7days'    => ['minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'month'     => ['minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'year'      => ['minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'last_year' => ['minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
            'total'     => ['minutes' => 0, 'hours' => 0,'full' => 0,'half' => 0],
        );

        $full_day_minutes = $this->params->get('full_day_minutes');
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
        $joblogs = [];
        $joblog_array = [];
        foreach ($person->getJobs() as $job) {
            foreach ($job->getJobLogs() as $jl) {
                // TODO: Check state. I guess "COMPLETED" is the one to use.
                $joblogs[] = $jl;
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
                    if ($minutes > $half_day_minutes)
                        $summary['last_year']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['last_year']['half']++;
                }
                if ($out > $first_of_week) {
                    $summary['week']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['week']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['week']['half']++;
                }
                if ($out > $l7days) {
                    $summary['l7days']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['l7days']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['l7days']['half']++;
                }
                if ($out > $first_of_month) {
                    $summary['month']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['month']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['month']['half']++;
                }
                if ($out > $first_of_year) {
                    $summary['year']['minutes'] += $minutes;
                    if ($minutes > $half_day_minutes)
                        $summary['year']['full']++;
                    elseif ($minutes > 0
                            && $minutes <= $half_day_minutes)
                        $summary['year']['half']++;
                }
            }
        }
        $summary['week']['hours']      = $this->mToHm($summary['week']['minutes']);
        $summary['l7days']['hours']    = $this->mToHm($summary['l7days']['minutes']);
        $summary['month']['hours']     = $this->mToHm($summary['month']['minutes']);
        $summary['year']['hours']      = $this->mToHm($summary['year']['minutes']);
        $summary['last_year']['hours'] = $this->mToHm($summary['last_year']['minutes']);
        $summary['total_hours']['hours']     = $this->mToHm($summary['total']['minutes']);
        return [
            'joblog_array' => $joblog_array,
            'joblogs' => $joblogs,
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
