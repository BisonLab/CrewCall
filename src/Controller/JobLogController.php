<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Shift;
use App\Entity\Person;
use App\Form\JobLogType;

/**
 * Job controller.
 *
 * @Route("/admin/{access}/joblog", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class JobLogController extends CommonController
{
    /**
     * a Time Sheet.
     *
     * @Route("/", name="joblog_index", methods={"GET"})
     */
    public function indexAction(Request $request, $access)
    {
        $em = $this->getDoctrine()->getManager();
        $job = null;
        if ($job_id = $request->get('job')) {
            $job = $em->getRepository(Job::class)->find($job_id);
        }
        if (!$job)
            return $this->returnNotFound($request, 'No job to tie the log to');

        $joblogs = $job->getJobLogs();

        if ($this->isRest($access)) {
            return $this->render('joblog/_index.html.twig', array(
                'job' => $job,
                'joblogs' => $joblogs,
            ));
        }

        return $this->render('joblog/index.html.twig', array(
            'job' => $job,
            'joblogs' => $joblogs,
        ));
    }

    /**
     * Creates one or more new JobLog entries.
     * If it's gotten and posted with a shift, add a JobLog with the same
     * in/out on all jobs in the shift.
     * And if it's a Job, only add to that.
     *
     * @Route("/new", name="joblog_new", methods={"GET", "POST"})
     */
    public function newAction(Request $request, $access)
    {
        $em = $this->getDoctrine()->getManager();
        $joblog = new JobLog();

        $job = null;
        if ($job_id = $request->get('job')) {
            $job = $em->getRepository(Job::class)->find($job_id);
        }
        $shift = null;
        if ($shift_id = $request->get('shift')) {
            $shift = $em->getRepository(Shift::class)->find($shift_id);
        }

        if (!$job && !$shift)
            return $this->returnNotFound($request, 'No job to tie the log to');

        if ($shift) {
            $joblog->setIn($shift->getStart());
            $joblog->setOut($shift->getEnd());
        } else {
            $joblog->setJob($job);
            $joblog->setIn($job->getShift()->getStart());
            $joblog->setOut($job->getShift()->getEnd());
        }
        $form = $this->createForm(JobLogType::class, $joblog);

        if ($this->isRest($access)) {
            return $this->render('joblog/_new.html.twig', array(
                'job' => $job,
                'shift' => $shift,
                'form' => $form->createView(),
            ));
        }

        return $this->render('joblog/new.html.twig', array(
            'job' => $job,
            'shift' => $shift,
            'form' => $form->createView(),
        ));
    }

    /**
     * Creates one or more new JobLog entries.
     * I am splitting new and create as good old symfony.
     * Just too much code in the submit part to keep all in one.
     *
     * @Route("/create", name="joblog_create", methods={"GET", "POST"})
     */
    public function createAction(Request $request, $access)
    {
        $em = $this->getDoctrine()->getManager();
        $joblog = new JobLog();
        $form = $this->createForm(JobLogType::class, $joblog);
        $form->handleRequest($request);

        $job = null;
        if ($job_id = $request->get('job')) {
            $job = $em->getRepository(Job::class)->find($job_id);
        }
        $shift = null;
        if ($shift_id = $request->get('shift')) {
            $shift = $em->getRepository(Shift::class)->find($shift_id);
        }

        if (!$job && !$shift)
            return $this->returnNotFound($request, 'No job to tie the log to');

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $request->request->get($form->getName());
            // We have a joblog, but is it a cheat or not?
            // First, check cheat, aka "All in the shift".
            if ($shift) {
                $in = new \DateTime($data['in']['date'] . " " . $data['in']['time']);
                $out = new \DateTime($data['out']['date'] . " " . $data['out']['time']);
                foreach($shift->getJobs(['booked' => true]) as $job) {
                    $joblog = new JobLog();
                    // This form has the dates and it all.
                    $joblog->setIn($in);
                    $joblog->setOut($out);
                    $joblog->setJob($job);
                    if ($overlap = $em->getRepository(JobLog::class)->checkOverlapForPerson($joblog)) {
                        return new Response("Found existing work in the timeframe you entered. Shift is " . (string)current($overlap)->getJob()->getShift() . " and person is " . (string)$job->getPerson(), Response::HTTP_CONFLICT);
                    }
                    $em->persist($joblog);
                }
            // And if not, this is just one persons in and out.
            } else {
                $joblog->setJob($job);
                // Check overlap.
                if ($overlap = $em->getRepository(JobLog::class)->checkOverlapForPerson($joblog)) {
                    return new Response("Found existing work in the timeframe you entered. Shift is " . (string)current($overlap)->getJob()->getShift(), Response::HTTP_CONFLICT);
                }
                $em->persist($joblog);
            }

            $em->flush();

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"),
                    Response::HTTP_CREATED);
            } else { 
                // Should I have non-rest at all?
                return $this->redirectToRoute('job_show',
                    array('id' => $job->getId()));
            }
        }
        $errors = $this->handleFormErrors($form);
        return new JsonResponse(array("status" => "ERROR",
            'errors' => $errors), 422);
    }

    /**
     * The time log per person.
     *
     * @Route("/{id}/person", name="joblog_person", methods={"GET"})
     */
    public function indexPersonAction(Request $request, $access, Person $person)
    {
        $handler = $this->get('crewcall.joblogs');
        $job = null;
        $options['summary_only'] = $request->get('summary_only');
        $options['from_date'] = $request->get('from_date');
        $options['to_date'] = $request->get('to_date');

        $logs = $handler->getJobLogsForPerson($person, $options);

        if ($this->isRest($access)) {
            return $this->render('joblog/_indexPerson.html.twig', array(
                'person' => $person,
                'joblogs' => $logs['joblogs'],
                'summary' => $logs['summary'],
            ));
        }

        return $this->render('joblog/indexPerson.html.twig', array(
            'person' => $person,
            'joblogs' => $logs['joblogs'],
            'summary' => $logs['summary'],
        ));
    }

    /**
     * Displays a form to edit an existing shift entity.
     *
     * @Route("/{id}/edit", name="joblog_edit", defaults={"id" = 0}, methods={"GET", "POST"})
     */
    public function editAction(Request $request, JobLog $joblog, $access)
    {
        $editForm = $this->createForm(JobLogType::class, $joblog);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }

        if ($this->isRest($access)) {
            return $this->render('joblog/_edit.html.twig', array(
                'joblog' => $joblog,
                'edit_form' => $editForm->createView(),
            ));
        }

        return $this->render('joblog/edit.html.twig', array(
            'joblog' => $joblog,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a joblog entity.
     *
     * @Route("/{id}", name="joblog_delete", methods={"DELETE", "POST"})
     */
    public function deleteAction(Request $request, $access, JobLog $joblog)
    {
        if (!$token = $request->request->get('_token')) {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_token'];
        }
        if (!$this->isCsrfTokenValid('deletejoblog'.$joblog->getId(), $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }
        if ($this->isRest($access)) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($joblog);
            $em->flush($joblog);
            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }
    }
}
