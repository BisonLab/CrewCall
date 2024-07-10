<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;
use App\Entity\Person;
use App\Entity\FunctionEntity;
use App\Lib\ExternalEntityConfig;

/*
 *
 */
class CrewJobs extends CommonReportFunctions
{
    protected $container;

    public function __construct($container, $options = array())
    {
        $this->container = $container;
    }

    // All fixed reports shall be hydrated as arrays.
    public function runFixedReport($config = null)
    {
        if (!$people = $config['people'] ?? null)
            throw new \InvalidArgumentException("You need to choose people, at least one person.");

        $em = $this->getManager();
        $personfields = $this->container->getParameter('personfields');
        $jobloghandler = $this->container->get('crewcall.joblogs');

        $header = [
            'Name',
            'Shift',
            'Shift Start',
            'Shift End',
            'Shift Duration',
            'First in',
            'Last out',
            'Break',
            'Minutes Worked',
            'Time Worked',
            'Percent Worked',
            'Resting Overlap',
        ];

        $qb = $em->createQueryBuilder();
        $qb->select('p')
            ->from(Person::class, 'p')
            ->where('p.id in (:people)')
            ->setParameter('people', $people);

        $options = [];
        $options['from_date'] = $config['from_date'] ? new \DateTime($config['from_date']) : null;
        $options['to_date'] = $config['to_date'] ? new \DateTime($config['to_date']) : null;

        $data = [];

        foreach ($qb->getQuery()->getResult() as $person) {
            // Old system
            foreach ($this->_getOldLogs($person) as $job) {
                $dato = new \DateTime($job['Dato']);
                if ($options['from_date'] && $options['from_date'] > $dato)
                    continue;
                if ($options['to_date'] && $options['to_date'] < $dato)
                    continue;

                $data[] = [
                    'Name' => (string)$person,
                    'Shift' => $job['Funksjon'] . " at " . $job['Artist'],
                    'Shift Start' => $job['Dato'] . " " . $job['Fra'],
                    'Shift End' => $job['Til'],
                    'Shift Duration' => $job['Skiftlengde'],
                    'First in' => $job['Inn1'],
                    'Last out' => $job['Ut2'],
                    'Break' => $job['Lunsj'],
                    'Minutes Worked' => $job['Worked Minus break'],
                    'Time Worked' => $job['Worked Minus break'],
                    'Percent Worked' => 'N/A',
                    'Overlap' => 'N/A'
                ];
            }

            $joblogs = $jobloghandler->getJobLogsForPerson($person, $options);
            foreach ($joblogs['jobs_array'] as $job) {
                $data[] = [
                    'Name' => (string)$person,
                    'Shift' => $job['shift'],
                    'Shift Start' => $job['shift_start'],
                    'Shift End' => $job['shift_end'],
                    'Shift Duration' => $job['shift_duration'],
                    'First in' => $job['first_in'],
                    'Last out' => $job['last_out'],
                    'Break' => $job['breakminutes'],
                    'Minutes Worked' => $job['workedminutes'],
                    'Time Worked' => $job['workedtime'],
                    'Percent Worked' => $job['percent_worked'],
                    'Overlap' => $job['overlap'],
                ];
            }
        }
        return ['data' => $data, 'header' => $header];
    }

    private function _getOldLogs($person)
    {
        $ansattid = null;
        $logs = [];
        foreach ($person->getContexts() as $c) {
            if ($c->getSystem() == "old_one")
                $ansattid = $c->getExternalId();
        }
        if (!$ansattid)
            return $logs;

        $this->em = $this->container->get('doctrine')->getManager();
        $this->sted_manager      = $this->container->get("sted_manager");
        $this->underarr_manager  = $this->container->get("underarr_manager");
        $this->arrangement_manager  = $this->container->get("arrangement_manager");
        $this->sted_manager  = $this->container->get("sted_manager");
        $this->rolle_manager  = $this->container->get("rolle_manager");
        $this->arrangement_manager  = $this->container->get("arrangement_manager");
        $this->interesse_manager = $this->container->get("interesse_manager");
        $this->function_repo     = $this->em->getRepository(FunctionEntity::class);

        foreach ($this->interesse_manager->findByKeyVal('AnsattID', $ansattid) as $interesse) {
            $log = [];
            if ($interesse['Tildelt']) {
                $state = 'CONFIRMED';
            } else {
                continue;
            }
            if (!$log = $this->_findArrData($interesse['UnderID']))
                continue;

            $date = new \DateTime($log['Dato']);
            $cut  = new \DateTime("2017-01-01");
            if ($date < $cut)
                continue;

            $shift_from = new \DateTime($log['Dato'] . " " . $log['Fra']);
            $shift_to = new \DateTime($log['Dato'] . " " . $log['Til']);
            if ($shift_to < $shift_from) $shift_to->modify("+1day");
            $shift_minutes =  ($shift_to->getTimeStamp() - $shift_from->getTimeStamp()) / 60;
            $h = floor($shift_minutes / 60);
            $m = $shift_minutes % 60;
            $shift_time = $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);

            $Inn1 = new \DateTime($log['Dato'] . " " . $interesse['Inn1']);
            $Ut1 = new \DateTime($log['Dato'] . " " . $interesse['Ut1']);
            if ($Ut1 < $Inn1) $Ut1->modify("+1day");
            $Inn2 = new \DateTime($log['Dato'] . " " . $interesse['Inn2']);
            $Ut2 = new \DateTime($log['Dato'] . " " . $interesse['Ut2']);
            if ($Ut2 < $Inn2) $Ut2->modify("+1day");

            $w1 =  ($Ut1->getTimeStamp() - $Inn1->getTimeStamp()) / 60;
            $w2 =  ($Ut2->getTimeStamp() - $Inn2->getTimeStamp()) / 60;
            $worked_minutes = $w1 + $w2;
            $h = floor($worked_minutes / 60);
            $m = $worked_minutes % 60;
            $worked_time = $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);

            list($lt, $lm) = explode(":", $interesse['Lunsj']);
            $lunsj_mins = ($lt * 60) + $lm;
            $worked_minus_break_minutes = $w1 + $w2 - $lunsj_mins;
            $h = floor($worked_minus_break_minutes / 60);
            $m = $worked_minus_break_minutes % 60;
            $worked_minus_break = $h . ":" . str_pad($m, 2, "0", STR_PAD_LEFT);

            $log['Skiftlengde'] = $shift_time;
            $log['State'] = $state;
            $log['Inn1'] = $interesse['Inn1'];
            $log['Ut1'] = $interesse['Ut1'];
            $log['Inn2'] = $interesse['Inn2'];
            $log['Ut2'] = $interesse['Ut2'];
            $log['Lunsj'] = $interesse['Lunsj'];
            $log['Worked'] = $worked_time;
            $log['Worked Minus break'] = $worked_minus_break;
            $logs[] = $log;
        }

        return $logs;
    }

    private function _findArrData($u_id)
    {
        $arrdata = [];
        if (!$underarr = $this->underarr_manager->findOneByKeyVal('ID', $u_id))
            return null;
        $arr = $this->arrangement_manager->findOneByKeyVal('ID', $underarr['ArrID']);
        $sted = $this->sted_manager->findOneById($arr['STE_ID']);
        $rolle = $this->rolle_manager->findOneById($underarr['ROL_ID']);

        $arrdata['Artist'] = iconv("iso-8859-1", "UTF-8", $arr['Artist']);
        $arrdata['Sted'] = iconv("iso-8859-1", "UTF-8", $sted['STE_TITLE']);
        $arrdata['Funksjon'] = iconv("iso-8859-1", "UTF-8", $rolle['ROL_TITLE']);
        $arrdata['Dato'] = $arr['Dato'];
        $arrdata['Fra'] = $underarr['Fra'];
        $arrdata['Til'] = $underarr['Til'];

        return $arrdata;
    }
}
