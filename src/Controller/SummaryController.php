<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Event;
use App\Entity\Person;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\Organization;
use App\Entity\Location;
use App\Service\Jobs;
use App\Service\Summarizer;

/**
 * Summary / Summarizer controller.
 */
#[Route(path: '/admin/{access}/summary', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class SummaryController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
        private Summarizer $summarizer,
    ) {
    }

    /**
     * Summary with proper path.
     */
    #[Route(path: '/entity/{entity}/entity_id/{id}', name: 'summary_show', methods: ['GET'])]
    public function showAction(Request $request, $access, $entity, $id)
    {
        return $this->_show($request, $access, $entity, $id);
    }

    
    #[Route(path: '/', name: 'summary_show_get', methods: ['GET'])]
    public function getAction(Request $request, $access)
    {
        if (!$entity = $request->get("entity"))
            return $this->returnNotFound($request, 'Unable to find entity.');
        if (!$id = $request->get("entity_id"))
            return $this->returnNotFound($request, 'Unable to find entity.');

        return $this->_show($request, $access, $entity, $id);
    }

    /**
     *
     * I guess this should be configureable based on the users preference.
     *
     * Right now it's -2 +3 days and assigned and confirmed jobs.
     * (I guess it should be booked, but this is more useful for my current
     * users)
     */
    #[Route(path: '/person_jobs_job', name: 'summary_person_jobs_job', methods: ['GET'])]
    public function personJobsJobAction(Request $request, $access, Jobs $ccjobs)
    {
        if (!$job = $this->entityManager->getRepository(Job::class)->find($request->get("job")))
            return $this->returnNotFound($request, 'Unable to find job.');
        $options = [];
        $from = clone($job->getStart());
        $options['from'] = $from->modify('-2 days');
        $to = clone($job->getStart());
        $options['to'] = $to->modify('+3 days');
        $summary_datesorted = [];
        $summary_booked = [];
        $summary_interested = [];
        $person = $job->getPerson();
        foreach($ccjobs->jobsForPerson(
            $person, $options) as $job) {
                $label = (string)$job . " at " . (string)$job->getEvent();
                $value = $job->getStart()->format("d M H:i")
                    . " -> " .
                    $job->getEnd()->format("d M H:i")
                    . "(" . $job->getStateLabel() . ")";
                $arr = [
                        'label' => $label,
                        'value' => $value
                        ];
                $summary_datesorted[] = $arr;
                if ($job->isBooked()) {
                    $summary_booked[] = $arr;
                } else {
                    $summary_interested[] = $arr;
                }
        }
        $summary = array_merge($summary_booked, $summary_interested);
        if (count($summary) == 0)
            $summary[] = ['label' => "No jobs for this period", 'value' => ""];

        /*
         * Yes, this should be a config option since I have the options..
         */
        return $this->returnRestData($request, $summary_datesorted,
            array('html' => 'summaryPopContent.html.twig'));
    }

    private function _show($request, $access, $entity, $id)
    {
        $summary = null;
        // Switch it.
        switch ($entity) {
            case 'person':
                $entity = $this->entityManager->getRepository(Person::class)->find($id);
                break;
            // Feels wrong, but it's kinda effective and is reuse. Only lists the jobs now +2 days.
            case 'person_jobs':
                $entity = $this->entityManager->getRepository(Person::class)->find($id);
                $summary = $this->personJobs($entity);
                break;
            case 'event':
                $entity = $this->entityManager->getRepository(Event::class)->find($id);
                break;
            default:
                return $this->returnNotFound($request,'Unable to find class.');
                break;
        }

        if (!$entity) {
            return $this->returnNotFound($request, 'Unable to find entity.');
        }
        if (!$summary)
            $summary = $this->summarizer->summarize($entity, $access);
        if ($this->isRest($access)) {
            return $this->returnRestData($request, $summary,
                array('html' => 'summaryPopContent.html.twig'));
        }
    }

    /*
     * Showing the content of Gedmo Loggable. As a kinda summary.
     * (Rationalizing why it's even here)
     */
    
    #[Route(path: '/log', name: 'summary_show_log', methods: ['GET'])]
    public function logSummaryAction(Request $request, $access)
    {
        if (!$entity = $request->get("entity"))
            return $this->returnNotFound($request, 'No entity.');
        if (!$id = $request->get("entity_id"))
            return $this->returnNotFound($request, 'No entity_id.');

        return $this->_showLogSummary($request, $access, $entity, $id);
    }

    private function _showLogSummary($request, $access, $entity, $id)
    {
        // Switch it.
        switch ($entity) {
            case 'shift':
                $class = Shift::class;
                break;
            case 'organization':
                $class = Organization::class;
                break;
            case 'location':
                $class = Location::class;
                break;
            case 'person':
                $class = Person::class;
                break;
            case 'event':
                $class = Event::class;
                break;
            case 'job':
                $class = Job::class;
                break;
            default:
                return $this->returnNotFound($request,'Unable to find class.');
                break;
        }
        return $this->showLogPage($request,$access, $class, $id,
            ['html' => 'summaryLogPopContent.html.twig']);
    }
    
    /*
     * Let's call this "Local custom helpers"
     */
    public function personJobs($person, Jobs $ccjobs)
    {
        $options = [];
        // I'll default today +2 days. Add options at will and need.
        $options['from'] = new \DateTime();
        $options['to'] = new \DateTime('+2days');
        $summary = [];
        foreach($ccjobs->jobsForPerson($person, $options) as $job) {
                $label = (string)$job . " at " . (string)$job->getEvent();
                $value = $job->getStart()->format("d M H:i")
                    . " -> " .
                    $job->getEnd()->format("d M H:i")
                    . "(" . $job->getState() . ")";
                $summary[] = [
                    'label' => $label,
                    'value' => $value
                    ];
        }
        if (count($summary) == 0)
            $summary[] = ['label' => "No jobs for this period", 'value' => ""];
        return $summary;
    }
}
