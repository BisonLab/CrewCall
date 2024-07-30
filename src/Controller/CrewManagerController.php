<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Shift;
use App\Entity\Event;
use App\Service\Jobs;

/**
 * Crew Manager controller.
 * This is the controller for the front end par of the application.
 *
 * It's the only one for now, and may be pushed onto it's own bundle in case
 * someone means we need different front ends. Which might be true, as this
 * starts very simple as I just need it for testing functionality.
 */
#[Route(path: '/crewman')]
class CrewManagerController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    /**
     * My crews jobs
     */
    #[Route(path: '/my_crew', name: 'cm_my_crew', methods: ['GET'])]
    public function myCrew(Request $request, Jobs $ccjobs, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $tokenManager)
    {
        // Create a csrf token for use in the next step
        $user = $this->getUser();

        // To be honest, I don't really know how it would be possible to be
        // crew manager on more than one event at the same time, but, well.
        $events = new ArrayCollection();
        // TODO: GOtta add a check on crewManager on all events of the day.

        $date = new \DateTime();
        if ($shift_id = $request->get('shift')) {
            if (!$shift = $entityManager->getRepository(Shift::class)
                ->find($shift_id))
                throw $this->createNotFoundException('Shift not found');
            if (!$user->isCrewManager($shift))
                throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('No will do');
            $events->add($shift->getEvent());
            $date = $shift->getStart();
        } else {
            if ($request->get('date'))
                $date = new \DateTime($request->get('date'));
            $dated_events = $entityManager->getRepository(Event::class)
                ->findEvents([
                    'on_date' => $date,
                    'booked' => true,
                    ]);
            foreach ($dated_events as $de) {
                if ($user->isCrewManager($de) && !$events->contains($de))
                    $events->add($de);
            }
        }

        $shifts = [];
        $signintoken = $tokenManager->getToken('signin-job')->getValue();
        $signouttoken = $tokenManager->getToken('signout-job')->getValue();
        $deletejoblogtoken = $tokenManager->getToken('delete-joblog')->getValue();
        foreach ($events as $event) {
            foreach ($event->getShifts() as $shift) {
                $jobs = $shift->getJobs(['booked' => true]);
                if (count($jobs) > 0)
                    $shifts[] = ['shift' => $shift, 'jobs' => $jobs];
            }
        }

        $retarr = [
            'day' => $date->format('Y-m-d'),
            'day_before' => $date->modify('-1 day')->format('Y-m-d'),
            'day_after' => $date->modify('+2 day')->format('Y-m-d'),
            'crewshifts' => $shifts,
            'signintoken' => $signintoken,
            'signouttoken' => $signouttoken,
            'deletejoblogtoken' => $deletejoblogtoken,
            ];

        return $this->render('crewman/_crew.html.twig', $retarr);
    }

    
    #[Route(path: '/signin/{id}', name: 'cm_signin_job', methods: ['POST'])]
    public function signinJobAction(Request $request, Job $job, EntityManagerInterface $entityManager)
    {
        $user = $this->getUser(); 
        if (!$user->isCrewManager($job->getShift()))
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('No will do');

        if ($token = $request->request->get('_csrf_token')) {
            $time = $request->request->get('time');
        } else {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
            $time = $json_data['time'];
        }
        if (!$this->isCsrfTokenValid('signin-job', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        if ($last_joblog = $job->getJobLogs()->last()) {
            if (!$last_joblog->getOut()) {
                return new JsonResponse(["ERRROR" => "Gotta sign out first."], Response::HTTP_FORBIDDEN);
            }
        }

        $joblog = new JobLog();
        $joblog->setJob($job);
        $joblog->setInTime($time);

        $entityManager->persist($joblog);
        $entityManager->flush();
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    
    #[Route(path: '/signout/{id}', name: 'cm_signout_job', methods: ['POST'])]
    public function signoutJobAction(Request $request, Job $job, EntityManagerInterface $entityManager)
    {
        $user = $this->getUser(); 
        if (!$user->isCrewManager($job->getShift()))
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('No will do');

        if ($token = $request->request->get('_csrf_token')) {
            $time = $request->request->get('time');
        } else {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
            $time = $json_data['time'];
        }

        if (!$this->isCsrfTokenValid('signout-job', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        if (!$last_joblog = $job->getJobLogs()->last()) {
            return new JsonResponse(["ERRROR" => "No job to sign out"], Response::HTTP_FORBIDDEN);
        }
        if ($last_joblog->getOut()) {
            return new JsonResponse(["ERRROR" => "No job to sign out"], Response::HTTP_FORBIDDEN);
        }
        $last_joblog->setOutTime($time);

        $entityManager->persist($last_joblog);
        $entityManager->flush();
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    
    #[Route(path: '/delete_joblog/{id}', name: 'cm_delete_joblog', methods: ['DELETE', 'POST'])]
    public function deleteJobLogAction(Request $request, JobLog $joblog, EntityManagerInterface $entityManager)
    {
        $user = $this->getUser(); 
        $job = $joblog->getJob();
        if (!$user->isCrewManager($job->getShift()))
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('No will do');

        if (!$token = $request->request->get('_csrf_token')) {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
        }

        if (!$this->isCsrfTokenValid('delete-joblog', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($joblog);
        $entityManager->flush();
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }
}
