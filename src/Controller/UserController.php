<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Person;
use App\Entity\PersonState;
use App\Entity\Shift;
use App\Entity\Job;

use App\Model\FullCalendarEvent;

/**
 * User controller.
 * This is the controller for the front end par of the application.
 * 
 * It's the only one for now, and may be pushed onto it's own bundle in case
 * someone means we need different front ends. Which might be true, as this
 * starts very simple as I just need it for testing functionality.
 *
 * @Route("/user/{access}", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class UserController extends CommonController
{
    /**
     * @Route("/view", name="user_view")
     */
    public function index(Request $request): Response
    {
        // TODO: Check if crew should be true or not.
        $crew = $this->container->getParameter('enable_crew_manager');
        return $this->render('user/index.html.twig', [
            'crew' => $crew,
        ]);
    }

    /**
     * Lists all Jobs for a user.
     *
     * @Route("/me", name="user_me", methods={"GET"})
     */
    public function meAction(Request $request, $access)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        // Again, ajax-centric.
        if ($this->isRest($access)) {
            return $this->render('user/_me.html.twig', array(
                'past' => $request->get('past'),
                'user' => $user
            ));
        }

        return $this->render('user/me.html.twig', array(
            'past' => $request->get('past'),
            'user' => $user,
        ));
    }

    /**
     * Lists all the users jobs as calendar events.
     *
     * @Route("/me_calendar", name="user_me_calendar")
     */
    public function meCalendarAction(Request $request, $access)
    {
        $user = $this->getUser();
        // Gotta get the time scope.
        $from = $request->get('start');
        $to = $request->get('end');
        if ($this->isRest($access)) {
            $calendar = $this->container->get('crewcall.calendar');
            $jobservice = $this->container->get('crewcall.jobs');

            $jobs = $jobservice->jobsForPerson($user,
                array('all' => true, 'from' => $from, 'to' => $to));
            // $states = $user->getStates();
            $em = $this->getDoctrine()->getManager();
            $states = $em->getRepository(PersonState::class)
                ->findByPerson($user,
                array('from_date' => $from, 'to_date' => $to));
            // No idea why someone would want it, but useful for testing.
            if ($request->get('summary')) {
                $calitems = array_merge(
                    $calendar->toFullCalendarSummary($jobs, ['person' => $this->getUser()]),
                    $calendar->toFullCalendarSummary($states, ['person' => $this->getUser()])
                );
            } else {
                $calitems = array_merge(
                    $calendar->toFullCalendarArray($jobs, ['person' => $this->getUser()]),
                    $calendar->toFullCalendarArray($states, ['person' => $this->getUser()])
                );
            }
            // Not liked by OWASP since we just return an array.
            return new JsonResponse($calitems, Response::HTTP_OK);
        }
        return $this->render('user/calendar.html.twig', array(
            'user' => $user,
            'from' => $from,
            'to' => $to,
        ));
    }

    /**
     *
     * @Route("/confirm/{id}", name="user_confirm_job", methods={"POST"})
     */
    public function confirmJobAction(Request $request, Job $job, $access)
    {
        $user = $this->getUser();
        // TODO: Move to a service.
        $job->setState('CONFIRMED');
        $em = $this->getDoctrine()->getManager();
        $em->persist($job);
        $em->flush($job);
        if ($this->isRest($access)) {
            return new JsonResponse("OK", Response::HTTP_OK);
        }
        return $this->redirectToRoute('user_me');
    }

    /**
     *
     * @Route("/register_interest/{id}", name="user_register_interest", methods={"POST"})
     */
    public function registerInterestAction(Request $request, Shift $shift, $access)
    {
        $user = $this->getUser();

        $job = new Job();
        $job->setShift($shift);
        $job->setPerson($user);
        $job->setState('INTERESTED');
        $em = $this->getDoctrine()->getManager();
        $em->persist($job);
        $em->flush($job);
        return $this->redirectToRoute('user_me');
    }

    /**
     *
     * @Route("/job_calendaritem/{id}", name="user_job_calendar_item", methods={"GET"})
     */
    public function jobCaledarItemAction(Request $request, Job $job, $access)
    {
        $user = $this->getUser();
        // Better find the right exception later.
        if ($user->getId() != $job->getPerson()->getId())
            throw new \InvalidArgumentException("You are not the one to grab this.");

        $calendar = $this->container->get('crewcall.calendar');
        $ical = $calendar->toIcal($job);

        $response = new Response($ical, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="cal.ics"');
        return $response;
    }

    /**
     *
     * @Route("/delete_interest/{id}", name="user_delete_interest", methods={"DELETE", "POST"})
     */
    public function deleteInterestAction(Request $request, Job $job, $access)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        /*
         * In case of someone trying..
         * TODO: Decide on wether to add an isDeleteable() on Job and other
         * entities or do something else if it's smarter.
         * The reason is that it's not allowed to delete a confirmed job.
         */
        if ($job->getPerson() !== $user) {
            throw new \InvalidArgumentException('Nice try');
        }
        $em->remove($job);
        $em->flush($job);
        return $this->redirectToRoute('user_me');
    }
}
