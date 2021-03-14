<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Crew Manager controller.
 * This is the controller for the front end par of the application.
 * 
 * It's the only one for now, and may be pushed onto it's own bundle in case
 * someone means we need different front ends. Which might be true, as this
 * starts very simple as I just need it for testing functionality.
 *
 * @Route("/crewman")
 */
class CrewManagerController extends CommonController
{
    /**
     * My crews jobs
     * @Route("/my_crew", name="cm_my_crew", methods={"GET"})
     */
    public function myCrew(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $ccjobs = $this->container->get('crewcall.jobs');
        // Create a csrf token for use in the next step
        $csrfman = $this->get('security.csrf.token_manager');
        $user = $this->getUser();

        $shifts = [];
        if ($shift_id  = $request->get('shift')) {
            if (!$shift = $em->getRepository('App:Shift')
                ->find($shift_id))
                throw $this->createNotFoundException('Shift not found');
            if (!$user->isCrewManager($shift))
                throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('No will do');
            $shifts = [$shift];
        } else {
            $today = new \DateTime();
            $jobs = $em->getRepository('App:Job')
                ->findJobsForPerson($user, [
                    'from' => $today, 'to' => $today,
                    'state' => 'CONFIRMED']);
            foreach ($jobs as $job) {
                $shift = $job->getShift();
                if ($user->isCrewManager($shift))
                    $shifts[] = $shift;
            }
        }

        $jobs = [];
        foreach ($shifts as $shift) {
            $jobs = array_merge($jobs, $shift->getJobs->toArray());
        }

        $retarr = [
            'crewjobs' => $jobs,
            ];

        $signintoken = $csrfman->getToken('signin-job')->getValue();
        $retarr['signin_job'] = [
            '_csrf_token' => $signintoken,
            'url' => $this->generateUrl('cm_signin_job', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];

/*
        $signouttoken = $csrfman->getToken('signout-job')->getValue();
        $retarr['signout_job'] = [
            '_csrf_token' => $signouttoken,
            'url' => $this->generateUrl('cm_signout_job', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];
 */
        return $this->render('crewman/_crew.html.twig', $retarr);
    }

    /**
     *
     * @Route("/signin/{id}", name="cm_signin_job", methods={"POST"})
     */
    public function signinJobAction(Request $request, Job $job)
    {
        $user = $this->getUser(); 
        if (!$user->isCrewManager($job->getShift()))
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('No will do');

        if (!$token = $request->request->get('_csrf_token')) {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
        }

        if (!$this->isCsrfTokenValid('signin-job', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($job);
        $em->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }
}
