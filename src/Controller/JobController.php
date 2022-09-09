<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Shift;
use App\Entity\Event;
use App\Entity\Person;

/**
 * Job controller.
 *
 * @Route("/admin/{access}/job", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class JobController extends CommonController
{
    /**
     * Lists all job entities.
     *
     * @Route("/", name="job_index", methods={"GET"})
     */
    public function indexAction(Request $request, $access)
    {
        $em = $this->getDoctrine()->getManager();

        $shift = null;
        if ($shift_id = $request->get('shift')) {
            $em = $this->getDoctrine()->getManager();
            $shift = $em->getRepository(Shift::class)->find($shift_id);
        }
        /*
         * If you ask yourself why this is not set as a route option you are
         * into something. Reason is that there might be more than shifts for
         * filtering this.
         */
        if (!$shift)
            return $this->returnNotFound($request, 'No shift to tie the jobs to');
        $shiftamounts            = $shift->getJobsAmountByState();
        $shiftamounts['amount']  = $shift->getAmount();
        $shiftamounts['booked']  = $shift->getBookedAmount();
        $shiftamounts['needing'] = $shift->getAmount() - $shift->getBookedAmount();

        $jobs = $shift->getJobs(['sort_by' => 'last_name']);
        $sos = $shift->getShiftOrganizations();
        if ($this->isRest($access)) {
            return $this->render('job/_index.html.twig', array(
                'shiftamounts' => $shiftamounts,
                'shift' => $shift,
                'jobs'  => $jobs,
                'sos'   => $sos
            ));
        }

        return $this->render('job/index.html.twig', array(
            'shiftamounts' => $shiftamounts,
            'shift' => $shift,
            'jobs' => $jobs,
            'sos'  => $sos
        ));
    }

    /**
     *
     * @Route("/{id}/state/{state}", name="job_state", methods={"GET", "POST"})
     */
    public function stateAction(Request $request, Job $job, $state, $access)
    {
        $job->setState($state);
        $force = $request->get('force');
        
        $em = $this->getDoctrine()->getManager();

        $conflicts = [];
        if ($job->isBooked() && $overlap = $em->getRepository(Job::class)->checkOverlapForPerson($job, ['return_jobs' => true])) {
            foreach ($overlap as $ojob) {
                $overlapped = $ojob->getShift();
                $conflicts[] = 
                    "You are about to double book "
                    . (string)$job . " for "
                    . (string)$job->getPerson()
                    . " and the other job being "
                    . (string)$overlapped . " at "
                    . $overlapped->getStart()->format("H.i")
                    . " to " . $overlapped->getEnd()->format("H.i");
            }
        }

        if (!$force && count($conflicts) > 0) {
            return new Response(implode("\n", $conflicts), Response::HTTP_CONFLICT);
        }
        $em->persist($job);
        $em->flush($job);

        if ($this->isRest($access)) {
            $shiftamounts = $job->getShift()->getJobsAmountByState();
            $shiftamounts['amount'] = $job->getShift()->getAmount();
            $shiftamounts['booked'] = $job->getShift()->getBookedAmount();
            $shiftamounts['needing'] = $job->getShift()->getBookedAmount() - $job->getShift()->getBookedAmount();
            return new JsonResponse([
                "status" => "OK",
                "shiftamounts" => $shiftamounts
                ], Response::HTTP_CREATED);
        } else { 
            return $this->redirectToRoute('event_show', array('id' => $job->getEvent()->getId()));
        }
    }

    /**
     *
     * @Route("/states", name="jobs_state", methods={"POST"})
     */
    public function stateOnJobsAction(Request $request)
    {
        $jobs = $request->get('jobs');
        $state = $request->get('state');

        $em = $this->getDoctrine()->getManager();
        $jobrepo = $em->getRepository(Job::class);
        $conflicts = [];
        foreach ($jobs as $job_id) {
            if (!$job = $jobrepo->find($job_id))
                return new JsonResponse(array("status" => "NOT FOUND"), Response::HTTP_NOT_FOUND);
            $job->setState($state);
            if ($job->isBooked() && $overlap = $jobrepo->checkOverlapForPerson($job, ['return_jobs' => true])) {
                foreach ($overlap as $ojob) {
                    $overlapped = $ojob->getShift();
                    $conflicts[] = 
                        "You have now double booked "
                        . (string)$job . " for "
                        . (string)$job->getPerson()
                        . " and the other job being "
                        . (string)$overlapped . " at "
                        . $overlapped->getStart()->format("H.i")
                        . " to " . $overlapped->getEnd()->format("H.i");
                }
            }
        }
        $em->flush();
        if (count($conflicts) > 0) {
            return new Response(implode("\n", $conflicts), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(array("status" => "OK"), Response::HTTP_OK);
    }

    /**
     * Creates a new Job
     *
     * @Route("/new", name="job_new", methods={"GET", "POST"})
     */
    public function newAction(Request $request, $access)
    {
        $job = new Job();
        $form = $this->createForm('App\Form\JobType', $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $person_repo = $em->getRepository(Person::class);
            if (!$person = $person_repo->find($form->get('pname')->getData()))
                return $this->returnNotFound($request, 'No person to tie the jobs to');
            $job->setPerson($person);

            $conflicts = [];
            if ($job->isBooked() && $overlap = $em->getRepository(Job::class)->checkOverlapForPerson($job, ['return_jobs' => true])) {
                foreach ($overlap as $ojob) {
                    $overlapped = $ojob->getShift();
                    $conflicts[] = 
                        "You are about to double book "
                        . (string)$job . " for "
                        . (string)$job->getPerson()
                        . " and the other job being "
                        . (string)$overlapped . " at "
                        . $overlapped->getStart()->format("H.i")
                        . " to " . $overlapped->getEnd()->format("H.i");
                }
            }
            // Added to a function without having the skill?
            if (!$job->getPerson()->getFunctions()->contains($job->getShift()->getFunction())) {
                    $conflicts[] = 
                        "You are about to add "
                        . (string)$job->getPerson()
                        . " to a job with a function ("
                        . (string)$job->getFunction()
                        . ") the person does not have ";
            }

            $force = $request->get('force');
            if (!$force && count($conflicts) > 0) {
                return new Response(implode("\n", $conflicts), Response::HTTP_CONFLICT);
            }
            $em->persist($job);

            try {
                $em->flush($job);
            } catch (\Exception $e) {
                return new Response(
                    "Could not add Job. Shift possibly added to person already"
                  , Response::HTTP_BAD_REQUEST);
            }

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else { 
                return $this->redirectToRoute('job_show', array('id' => $job->getId()));
            }
        }

        // If this has a shift set here, it's not an invalid create attempt.
        if ($shift_id = $request->get('shift')) {
            $em = $this->getDoctrine()->getManager();
            if ($shift = $em->getRepository(Shift::class)->find($shift_id)) {
                $job->setShift($shift);
                $form->setData($job);
            }
        }
        if ($this->isRest($access)) {
            return $this->render('job/_new.html.twig', array(
                'job' => $job,
                'form' => $form->createView(),
            ));
        }
        return $this->render('job/new.html.twig', array(
            'job' => $job,
            'form' => $form->createView(),
        ));
    }

    /**
     *
     * @Route("/{id}/delete", name="job_delete", methods={"DELETE", "POST"})
     */
    public function deleteAction(Request $request, Job $job)
    {
        $token = $request->request->get('_csrf_token');
        if ($token && $this->isCsrfTokenValid('job-delete', $token)) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($job);
            $em->flush();
            return new JsonResponse(array("status" => "OK"), Response::HTTP_OK);
        }

        return new JsonResponse(array("status" => "Failed"), Response::HTTP_FORBIDDEN);
    }

    /**
     *
     * @Route("/release", name="jobs_release", methods={"POST"})
     */
    public function releaseJobsAction(Request $request)
    {
        $jobs = $request->get('jobs');

        $em = $this->getDoctrine()->getManager();
        $jobrepo = $em->getRepository(Job::class);
        foreach ($jobs as $job_id) {
            if (!$job = $jobrepo->find($job_id))
                return new JsonResponse(array("status" => "NOT FOUND"), Response::HTTP_NOT_FOUND);
            $em->remove($job);
        }
        $em->flush();

        return new JsonResponse(array("status" => "OK"), Response::HTTP_OK);
    }

    /**
     *
     * @Route("/move", name="jobs_move", methods={"POST"})
     */
    public function moveJobsAction(Request $request)
    {
        $moves = $request->get('moves');

        $em = $this->getDoctrine()->getManager();
        $jobrepo = $em->getRepository(Job::class);
        $shiftrepo = $em->getRepository(Shift::class);
        foreach ($moves as $job_id => $shift_id) {
            if (!$job = $jobrepo->find($job_id))
                return new JsonResponse(array("status" => "Job not found"),
                    Response::HTTP_NOT_FOUND);
            if (!$shift = $shiftrepo->find($shift_id))
                return new JsonResponse(array("status" => "Shift not found"),
                    Response::HTTP_NOT_FOUND);
            // Error if a job (person/shift combo) already exist or remove the
            // Job?
            if ($shift->getPeople()->contains($job->getPerson()))
                return new Response("Job already assiged", Response::HTTP_CONFLICT);

            $job->setShift($shift);
            // I will not check overlap, this is hopefully done by purpose.
        }
        $em->flush();

        return new JsonResponse(array("status" => "OK"), Response::HTTP_OK);
    }

    /**
     * Finds and displays the gedmo loggable history
     *
     * @Route("/{id}/log", name="job_log")
     */
    public function showLogAction(Request $request, $access, $id)
    {
        return  $this->showLogPage($request,$access, Job::class, $id);
    }

    /**
     * Sends messages to a batch of jobs.
     *
     * @Route("/jobs_send_message", name="jobs_send_message", methods={"POST"})
     */
    public function jobsSendMessageAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $jrepo = $em->getRepository(Job::class);
        $sm = $this->get('sakonnin.messages');
        $body = $request->request->get('body');
        $subject = $request->request->get('subject') ?? "Message from CrewCall";
        $job_ids = $request->request->get('jobs_list') ?? [];
        $person_ids = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($job_ids as $jid) {
            $job = $jrepo->find($jid);
            if ($person_ids->contains($job->getPerson()->getId()))
                continue;
            $person_ids->add($job->getPerson()->getId());
        }
        $message_type = $request->request->get('message_type');
        if ($person_ids->count() == 0)
            return new Response("No one to send to.", Response::HTTP_OK);
        $person_contexts = [];
        foreach ($person_ids as $pid) {
            $person_contexts[] = [
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $pid
            ];
        }
        $sm->postMessage(array(
            'subject' => $subject,
            'body' => $body,
            'from' => $this->parameter_bag->get('mailfrom'),
            'message_type' => $message_type,
            'to_type' => "INTERNAL",
            'from_type' => "INTERNAL",
        ), $person_contexts);
        $status_text = "Sent '".$body."' to " . count($person_contexts) . " persons.";
        return new Response($status_text, Response::HTTP_OK);
    }

    /*
     * Notes stuff.
     * I'd put it in a trait if it werent for it all being easier this way.
     */

    /**
     *
     * @Route("/{job}/add_note", name="job_add_note", methods={"POST"})
     */
    public function addNoteAction(Request $request, Job $job, $access)
    {
        $token = $request->request->get('_csrf_token');

        if ($token && $this->isCsrfTokenValid('job-add-note', $token)) {
            // Let's hope csrf token checks is enough.
            $job->addNote([
                'id' => $request->request->get('note_id'),
                'type' => $request->request->get('type'),
                'subject' => $request->request->get('subject'),
                'body' => $request->request->get('body')
            ]);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse([
                "status" => "OK",
                ], Response::HTTP_CREATED);
        }
        return new Response("Bad token", Response::HTTP_FORBIDDEN);
    }

    /**
     *
     * @Route("/{job}/{note_id}/edit_note", name="job_edit_note", methods={"POST"})
     */
    public function editNoteAction(Request $request, Job $job, $note_id, $access)
    {
        $token = $request->request->get('_csrf_token');

        if ($token && $this->isCsrfTokenValid('job-edit-note'.$note_id, $token)) {
            $job->updateNote([
                'id' => $note_id,
                'type' => $request->request->get('type'),
                'subject' => $request->request->get('subject'),
                'body' => $request->request->get('body')
            ]);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
        }
        return new JsonResponse([
            "status" => "OK",
            ], Response::HTTP_OK);
    }

    /**
     *
     * @Route("/{job}/{note_id}/remove_note", name="job_remove_note", methods={"POST"})
     */
    public function removeNoteAction(Request $request, Job $job, $note_id, $access)
    {
        $token = $request->request->get('_csrf_token');

        if ($token && $this->isCsrfTokenValid('job-remove-note'.$note_id, $token)) {
            $job->removeNote($note_id);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new Response("Deleted", Response::HTTP_NO_CONTENT);
        }
        return new Response("Bad token", Response::HTTP_FORBIDDEN);
    }
}
