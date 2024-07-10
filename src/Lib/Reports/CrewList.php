<?php

namespace App\Lib\Reports;

use Doctrine\ORM\EntityRepository;
use BisonLab\ReportsBundle\Lib\Reports\ReportsInterface;
use BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;
use App\Entity\Person;
use App\Lib\ExternalEntityConfig;

/*
 */
class CrewList extends CommonReportFunctions
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

        $header = [
            'Username',
            'First Name',
            'Last Name',
            'State',
            'Date of birth',
            'Age',
            'Mobile',
            'Email',
            'Last login',
            'Person Created',
            'Emergency Contact',
            'Total Confirmed Jobs',
        ];

        $qb = $em->createQueryBuilder();
        $qb->select('p')
           ->from(Person::class, 'p');

        if ($people = $config['people'] ?? null) {
            $qb->where('p.id in (:people)')
                ->setParameter('people', $people);
        }

        $data = [];
        $header_years = range(date('Y')-2, date('Y'));
        foreach ($qb->getQuery()->getResult() as $person) {
            if (($config['active_crew_only'] ?? false) && !$person->isActive())
                continue;
            $age = '';
            if ($dob = $person->getDateOfBirth()) {
                $age = $dob->diff(new \DateTime())->y;
                $dob = $dob->format('Y-m-d');
            }
            if ($ll = $person->getLastLogin()) {
                $ll = $ll->format('Y-m-d');
            }
            $log_repo = $em->getRepository('Gedmo\Loggable\Entity\LogEntry');
            $logs = $log_repo->findBy(array(
                'objectClass' => get_class($person),
                'objectId'    => $person->getId(),
                )
                , array('loggedAt' => 'ASC'));
            $created = $logs[0]->getLoggedAt()->format('Y-m-d');
            
            $arr = [
                'Username' => $person->getUsername(),
                'First Name' => $person->getFirstName(),
                'Last Name' => $person->getLastName(),
                'Mobile' => $person->getMobilePhoneNumber(),
                'Email' => $person->getEmail(),
                'State' => $person->getState(),
                'Date of birth' => $dob,
                'Age' => $age,
                'Last login' => $ll,
                'Person Created' => $created,
                'Emergency Contact' => $person->getEmergencyContact(),
            ];
            $arr['Total Confirmed Jobs'] = $person->getJobs(['state' => 'CONFIRMED'])->count();

            // Aww, so simple :=)
            $years = [];
            foreach ($header_years as $hy) { $years[$hy] = 0; }
            foreach ($person->getJobs(['state' => 'CONFIRMED']) as $job) {
                $year = $job->getStart()->format('Y');
                $years[$year]++;
            }
            $data[] = array_merge($arr, $years);
        }
        return ['data' => $data, 'header' => array_merge($header, $header_years)];
    }
}
