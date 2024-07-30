<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use App\Entity\Person;
use App\Entity\PersonState;
use App\Entity\Shift;
use App\Entity\Job;
use App\Model\FullCalendarEvent;
use App\Service\Jobs as CcJobs;
use App\Service\Calendar as CcCalendar;

/**
 * User controller.
 * This is the controller for the front end par of the application.
 *
 * It's the only one for now, and may be pushed onto it's own bundle in case
 * someone means we need different front ends. Which might be true, as this
 * starts very simple as I just need it for testing functionality.
 */
#[Route(path: '/user/{access}', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class UserController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private CcJobs $ccJobs,
        private CcCalendar $ccCalendar,
    ) {
    }

    #[Route(path: '/view', name: 'user_view')]
    public function index(Request $request): Response
    {
        // TODO: Check if crew should be true or not.
        $crew = $this->parameterBag->get('enable_crew_manager');
        return $this->render('user/index.html.twig', [
            'crew' => $crew,
        ]);
    }

    /**
     * Lists all Jobs for a user.
     */
    #[Route(path: '/me', name: 'user_me', methods: ['GET'])]
    public function meAction(Request $request, $access)
    {
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
     */
    #[Route(path: '/me_calendar', name: 'user_me_calendar')]
    public function meCalendarAction(Request $request, $access)
    {
        $user = $this->getUser();
        // Gotta get the time scope.
        $from = $request->get('start');
        $to = $request->get('end');
        if ($this->isRest($access)) {
            $jobs = $this->CcJobs->jobsForPerson($user,
                array('all' => true, 'from' => $from, 'to' => $to));
            // $states = $user->getStates();
            $states = $this->entityManager->getRepository(PersonState::class)
                ->findByPerson($user,
                array('from_date' => $from, 'to_date' => $to));
            // No idea why someone would want it, but useful for testing.
            if ($request->get('summary')) {
                $calitems = array_merge(
                    $this->ccCalendar->toFullCalendarSummary($jobs, ['person' => $this->getUser()]),
                    $this->ccCalendar->toFullCalendarSummary($states, ['person' => $this->getUser()])
                );
            } else {
                $calitems = array_merge(
                    $this->ccCalendar->toFullCalendarArray($jobs, ['person' => $this->getUser()]),
                    $this->ccCalendar->toFullCalendarArray($states, ['person' => $this->getUser()])
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

    
    #[Route(path: '/confirm/{id}', name: 'user_confirm_job', methods: ['POST'])]
    public function confirmJobAction(Request $request, Job $job, $access)
    {
        $user = $this->getUser();
        // TODO: Move to a service.
        $job->setState('CONFIRMED');
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);
        if ($this->isRest($access)) {
            return new JsonResponse("OK", Response::HTTP_OK);
        }
        return $this->redirectToRoute('user_me');
    }

    
    #[Route(path: '/register_interest/{id}', name: 'user_register_interest', methods: ['POST'])]
    public function registerInterestAction(Request $request, Shift $shift, $access)
    {
        $user = $this->getUser();

        $job = new Job();
        $job->setShift($shift);
        $job->setPerson($user);
        $job->setState('INTERESTED');
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);
        return $this->redirectToRoute('user_me');
    }
    
    #[Route(path: '/job_calendaritem/{id}', name: 'user_job_calendar_item', methods: ['GET'])]
    public function jobCaledarItemAction(Request $request, Job $job, $access)
    {
        $user = $this->getUser();
        // Better find the right exception later.
        if ($user->getId() != $job->getPerson()->getId())
            throw new \InvalidArgumentException("You are not the one to grab this.");

        $ical = $this->ccCalendar->toIcal($job);

        $response = new Response($ical, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="cal.ics"');
        return $response;
    }

    
    #[Route(path: '/delete_interest/{id}', name: 'user_delete_interest', methods: ['DELETE', 'POST'])]
    public function deleteInterestAction(Request $request, Job $job, $access)
    {
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
        $this->entityManager->remove($job);
        $this->entityManager->flush($job);
        return $this->redirectToRoute('user_me');
    }
}
