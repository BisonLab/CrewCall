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
class CrewJobs implements ReportsInterface
{
    use \BisonLab\ReportsBundle\Lib\Reports\CommonReportFunctions;

    public function getReportName(): string
    {
        return "CrewJobs";
    }

    public function getDescription(): string
    {
        return "Jobs listings per picked person";
    }

    public function getCriterias(): array
    {
        return ['people'];
    }

    public function getRequiredOptions(): array
    {
        return ['people'];
    }

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
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
}
