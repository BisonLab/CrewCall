<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Event;
use App\Entity\Person;
use App\Entity\Job;

/**
 * Summary / Summarizer controller.
 *
 * @Route("/admin/{access}/summary", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class SummaryController extends CommonController
{
    /**
     * Summary with proper path.
     *
     * @Route("/entity/{entity}/entity_id/{id}", name="summary_show", methods={"GET"})
     */
    public function showAction(Request $request, $access, $entity, $id)
    {
        return $this->_show($request, $access, $entity, $id);
    }

    /**
     *
     * @Route("/", name="summary_show_get", methods={"GET"})
     */
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
     * @Route("/person_jobs_job", name="summary_person_jobs_job", methods={"GET"})
     */
    public function personJobsJobAction(Request $request, $access)
    {
        $em = $this->getDoctrine()->getManager();
        if (!$job = $em->getRepository(Job::class)->find($request->get("job")))
            return $this->returnNotFound($request, 'Unable to find job.');
        $options = [];
        // I'll default today +2 days. Add options at will and need.
        $options['from'] = $job->getStart()->setTime(0, 0);
        $to = clone($job->getStart());
        $options['to'] = $to->modify('+1 day');
        $summary = [];
        $person = $job->getPerson();
        foreach($this->get('crewcall.jobs')->jobsForPerson(
            $person, $options) as $job) {
                $label = (string)$job . " at " . (string)$job->getEvent();
                $value = $job->getStart()->format("d M H:i")
                    . " -> " .
                    $job->getEnd()->format("d M H:i")
                    . "(" . $job->getStateLabel() . ")";
                $summary[] = [
                    'label' => $label,
                    'value' => $value
                    ];
        }
        if (count($summary) == 0)
            $summary[] = ['label' => "No jobs for this period", 'value' => ""];
        return $this->returnRestData($request, $summary,
            array('html' => 'summaryPopContent.html.twig'));
    }

    private function _show($request, $access, $entity, $id)
    {
        $summary = null;
        $em = $this->getDoctrine()->getManager();
        // Switch it.
        switch ($entity) {
            case 'person':
                $entity = $em->getRepository(Person::class)->find($id);
                break;
            // Feels wrong, but it's kinda effective and is reuse. Only lists the jobs now +2 days.
            case 'person_jobs':
                $entity = $em->getRepository(Person::class)->find($id);
                $summary = $this->personJobs($entity);
                break;
            case 'event':
                $entity = $em->getRepository(Event::class)->find($id);
                break;
            default:
                return $this->returnNotFound($request,'Unable to find class.');
                break;
        }

        if (!$entity) {
            return $this->returnNotFound($request, 'Unable to find entity.');
        }
        if (!$summary)
            $summary = $this->get('crewcall.summarizer')->summarize($entity, $access);
        if ($this->isRest($access)) {
            return $this->returnRestData($request, $summary,
                array('html' => 'summaryPopContent.html.twig'));
        }
    }

    /*
     * Showing the content of Gedmo Loggable. As a kinda summary.
     * (Rationalizing why it's even here)
     */

    /**
     *
     * @Route("/log", name="summary_show_log", methods={"GET"})
     */
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
                $class = 'App:Shift';
                break;
            case 'organization':
                $class = 'App:Organization';
                break;
            case 'location':
                $class = 'App:Location';
                break;
            case 'person':
                $class = 'App:Person';
                break;
            case 'event':
                $class = 'App:Event';
                break;
            case 'job':
                $class = 'App:Job';
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
    public function personJobs($person)
    {
        $options = [];
        // I'll default today +2 days. Add options at will and need.
        $options['from'] = new \DateTime();
        $options['to'] = new \DateTime('+2days');
        $summary = [];
        foreach($this->get('crewcall.jobs')->jobsForPerson(
            $person, $options) as $job) {
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
