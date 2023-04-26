<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Person;
use App\Entity\Event;
use App\Entity\Shift;
use App\Entity\Job;
use App\Entity\JobLog;
use App\Form\ChangePasswordFosType;
use App\Form\ChangePasswordFormType;
use App\Form\EditMyselfType;
use App\Model\FullCalendarEvent;

/**
 * User controller.
 * This is the controller for the front end par of the application.
 * 
 * It's the only one for now, and may be pushed onto it's own bundle in case
 * someone means we need different front ends. Which might be true, as this
 * starts very simple as I just need it for testing functionality.
 *
 * @Route("/uf")
 */
class UserFrontController extends CommonController
{
    private $shiftcache = [];
    private $eventcache = [];

    /**
     * Login
     *
     * @Route("/login", name="uf_login", methods={"GET"})
     */
    public function loginAction(Request $request)
    {
        // Create a csrf token for use in the next step
        $csrfman = $this->get('security.csrf.token_manager');
        $csrfToken = $csrfman->getToken('authenticate')->getValue();

        return new JsonResponse([
            '_csrf_token' => $csrfToken,
            '_username' => "",
            "_password" => '',
            "_remember_me" => "on",
            "login_url" => $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            Response::HTTP_OK);
    }

    /**
     * Ping
     *
     * @Route("/ping", name="uf_ping", methods={"GET"})
     */
    public function pingAction(Request $request)
    {
        return new JsonResponse([
            'ACK' => true,
            ],
            Response::HTTP_OK);
    }

    /**
     * Everything, and maybe more.
     *
     * @Route("/me", name="uf_me", methods={"GET"})
     */
    public function meAction(Request $request)
    {
        $user = $this->getUser();
        if (in_array('application/json',
                $request->getAcceptableContentTypes())) {
            $retarr = [
                'firstname' => $user->getFirstName(),
                'lastname' => $user->getLastName(),
            ];
            return new JsonResponse($retarr, 200);
        }
/*
        $from = new \DateTime();
        $to = new \DateTime('+1 year');
        $retarr = [
            'notes' => $this->meNotes($request, true),
            'messages' => $this->meMessages($request, true),
            'user' => $user
        ];
        $retarr['confirmed'] = $this->jobsForPersonAsArray($user, [
            'from' => $from, 'to' => $to,
            'state' => 'CONFIRMED']);
        $retarr['confirmed_count'] = count($retarr['confirmed']);
 */
        $retarr = $this->meJobs($request, true);
        $retarr['notes'] = $this->meNotes($request, true);
        $retarr['messages'] = $this->meMessages($request, true);
        $retarr['user'] = $user;
        return $this->render('user/_home.html.twig', $retarr);
    }

    /**
     * Everything, and maybe more.
     *
     * @Route("/me_profile", name="uf_me_profile", methods={"GET"})
     */
    public function meProfileAction(Request $request)
    {
        $user = $this->getUser();
        $sakonnin_files = $this->container->get('sakonnin.files');
        $addressing = $this->container->get('crewcall.addressing');
        $pfiles = $sakonnin_files->getFilesForContext([
                'file_type' => 'ProfilePicture',
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $user->getId()
            ]);
        $profile_picture_url = null;
        if (count($pfiles) > 0) {
            $router = $this->container->get('router');
            $profile_picture_url = $router->generate('uf_file', [
                'id' => end($pfiles)->getFileId(), 'x' => 200, 'y' => 200]);
        }

        $retarr = [
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'diets' => $user->getDietsLabels(),
            'email' => $user->getEmail(),
            'mobile_phone_number' => $user->getMobilePhoneNumber(),
            'profile_picture_url' => $profile_picture_url,
            'address' => [],
            'functions' => [],
        ];
        if ($address = $user->getAddress()) {
            $retarr['address'] = $addressing->compose($address);
            $retarr['address_flat'] = $addressing->compose($address, 'flat');
        }
        foreach ($user->getStates() as $ps) {
            if ($ps->isActive()) continue;
            $to_date = "";
            if ($to_date = $ps->getToDate())
                $to_date = $ps->getToDate()->format('Y-m-d');
            $from_date = "";
            if ($from_date = $ps->getFromDate())
                $from_date = $ps->getFromDate()->format('Y-m-d');
            $retarr['absence'][] = [
                'reason' => ucfirst(strtolower($ps->getState())),
                'state' => $ps->getStateLabel(),
                'from_date' => $from_date,
                'to_date' => $to_date,
            ];
        }
        foreach ($user->getPersonFunctions() as $pf) {
            $retarr['functions'][] = (string)$pf;
        }
        foreach ($user->getPersonRoleOrganizations() as $pro) {
            $retarr['roles'][] = [
                'role' => (string)$pro->getRole(),
                'organization' => (string)$pro->getOrganization(),
                'description' => (string)$pro,
                // A BC:
                'function' => (string)$pro->getRole()
            ];
        }

        // Angularfrontend
        if (in_array('application/json', $request->getAcceptableContentTypes()))
            return new JsonResponse($retarr, 200);

        $retarr['user'] = $user;
        return $this->render('user/_profile.html.twig', $retarr);
    }

    /**
     * Notes
     * @Route("/me_notes", name="uf_me_notes", methods={"GET"})
     */
    public function meNotes(Request $request, $as_array = false)
    {
        $archive = $request->get('archive');
        $states = ['UNREAD', 'SENT', 'SHOW'];
        if ($archive)
            $states = ['ARCHIVED'];
        
        $user = $this->getUser();
        $sakonnin = $this->container->get('sakonnin.messages');
        $pncontext = [
            'system' => 'crewcall',
            'object_name' => 'person',
            'message_type' => 'PersonNote',
            'states' => $states,
            'external_id' => $user->getId(),
        ];
        $pnotes = [];
        foreach ($sakonnin->getMessagesForContext($pncontext) as $m) {
            $parr = [
                'subject' => $m->getSubject(),
                'body' => $m->getBody(),
                'state' => $m->getState(),
                'id' => $m->getId(),
                'date' => $m->getCreatedAt(),
                'createdAt' => $m->getCreatedAt(),
                'createdBy' => (string)$m->getCreatedBy(),
                'messagetype' => (string)$m->getMessageType(),
                'message_type' => (string)$m->getMessageType(),
                ];
            if (!$archive) {
                $parr['archive_url'] = $this->generateUrl('message_state', [
                    'access' => 'ajax',
                    'state' => 'ARCHIVED',
                    'message_id' => $m->getMessageId()
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL);
            }
            $pnotes[] = $parr;
        }

        $gnotes = [];
        $criterias = [
            'states' => $states,
            'message_type' => 'Front page logged in',
            'order' => 'DESC'
        ];
        foreach ($sakonnin->getMessages($criterias) as $m) {
            $gnotes[] = [
                'subject' => $m->getSubject(),
                'state' => $m->getState(),
                'body' => $m->getBody(),
                'id' => $m->getId(),
                'date' => $m->getCreatedAt(),
                'createdBy' => (string)$m->getCreatedBy(),
                'createdAt' => $m->getCreatedAt(),
                'messagetype' => (string)$m->getMessageType(),
                'message_type' => (string)$m->getMessageType(),
            ];
        }
        $retarr = [
            'personal' => $pnotes,
            'general' => $gnotes,
            ];

        if ($as_array)
            return $retarr;
        return new JsonResponse($retarr, 200);
    }

    /**
     * Messages part
     * @Route("/me_messages", name="uf_me_messages", methods={"GET"})
     */
    public function meMessages(Request $request, $as_array = false)
    {
        $user = $this->getUser();
        $sakonnin = $this->container->get('sakonnin.messages');
        $pmessages = [];

        foreach ($sakonnin->getMessagesForUser($user, ['state' => 'UNREAD']) as $m) {
            $pmessages[] = [
                'subject' => $m->getSubject(),
                'body' => $m->getBody(),
                'date' => $m->getCreatedAt(),
                'createdAt' => $m->getCreatedAt(),
                'createdBy' => (string)$m->getCreatedBy(),
                'messagetype' => (string)$m->getMessageType(),
                'message_type' => (string)$m->getMessageType(),
                'archive_url' => $this->generateUrl('message_state', [
                    'access' => 'ajax',
                    'state' => 'ARCHIVED',
                    'message_id' => $m->getMessageId()
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL)
                ];
        }
        $retarr = [
            'personal' => $pmessages,
        ];

        if ($as_array)
            return $retarr;
        return new JsonResponse($retarr, 200);
    }

    /**
     * Jobs part
     * @Route("/me_jobs", name="uf_me_jobs", methods={"GET"})
     */
    public function meJobs(Request $request, $as_array = false)
    {
        $ccjobs = $this->container->get('crewcall.jobs');
        // Create a csrf token for use in the next step
        $csrfman = $this->get('security.csrf.token_manager');
        $view = $request->get('view') ?? null;
        if ($view && !in_array($view, ['past', 'jobs_list', 'opportunities', 'interested', 'assigned', 'confirmed']))
            throw new \InvalidArgumentException("Funnily enough, I do not acceept your view.");

        $today = new \DateTime();
        $from = new \DateTime($request->get('from') ?? null);
        $to = new \DateTime($request->get('to') ?? '+1 year');
        $state = $request->get('state') ?? null;

        // Do not make the counter at the bottom base itself on just that month.
        $recount = true;
        if ($month = $request->get('month'))
            $recount = false;
        // This is only useful for opportunities, which can be way too much
        // otherwise.
        // Tro without setting a month. Default "Every single opportunity"
        // if (!$month && $view == 'opportunities') {
        //    $month = date("m");
        //    $recount = false;
        // }

        if ($month) {
            $year = date("Y");
            $now_month = date("m");
            if ($month < $now_month)
                $year++;
            $from = new \DateTime($year . "-" . $month);
            $to = clone($from);
            $to->modify('last day of this month');
        }

        // Either way, never go below today. Historical jobs will be handled
        // somewhere else or with a query option to override.
        if ($from < $today)
            $from = $today;

        $user = $this->getUser();
        // Should I add a "Limit"?

        $retarr = [
            'view' => $view,
            'month' => $month,
            'recount' => $recount,
            'period' => [ 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d') ],
            'state' => $state,
            'opportunities' => null,
            'opportunities_count' => 0,
            'interested' => null,
            'interested_count' => 0,
            'assigned' => null,
            'assigned_count' => 0,
            'confirmed' => null,
            'confirmed_count' => 0
            ];

        if ($view == "past") {
            $em = $this->getDoctrine()->getManager();
            $retarr['past'] = $em->getRepository(Job::class)
                ->findJobsForPerson($user, ['booked' => true, 'past' => true]);
            $retarr['past_count'] = count($retarr['past']);
            return $this->render('user/_' . $view . '.html.twig', $retarr);
        }

        $signuptoken = $csrfman->getToken('signup-shift')->getValue();
        $retarr['signup_shift'] = [
            '_csrf_token' => $signuptoken,
            'url' => $this->generateUrl('uf_signup_shift', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];
        $retarr['opportunities'] = $this->opportunitiesForPersonAsArray(
            $user,
            [ 'from' => $from, 'to' => $to, 'no_shift_data' => $as_array ]
            );
        $retarr['opportunities_count'] = count($retarr['opportunities']);
            
        $ditoken = $csrfman->getToken('delete-interest')->getValue();
        $retarr['delete_interest'] = [
            '_csrf_token' => $ditoken,
            'url' => $this->generateUrl('uf_delete_interest', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];
        $confirmtoken = $csrfman->getToken('confirm-job')->getValue();
        $retarr['confirm_job'] = [
            '_csrf_token' => $confirmtoken,
            'url' => $this->generateUrl('uf_confirm_job', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];

        $retarr['interested'] = $this->jobsForPersonAsArray($user, [
            'from' => $from, 'to' => $to,
            'state' => 'INTERESTED']);
        $retarr['interested_count'] = count($retarr['interested']);

        $retarr['assigned'] = $this->jobsForPersonAsArray($user, [
            'from' => $from, 'to' => $to,
            'state' => 'ASSIGNED']);
        $retarr['assigned_count'] = count($retarr['assigned']);

        $retarr['confirmed'] = $this->jobsForPersonAsArray($user, [
            'from' => $from, 'to' => $to,
            'state' => 'CONFIRMED']);
        $retarr['confirmed_count'] = count($retarr['confirmed']);

        if ($as_array)
            return $retarr;
        if (!$view || in_array('application/json',
                $request->getAcceptableContentTypes()))
            return new JsonResponse($retarr, 200);

        return $this->render('user/_' . $view . '.html.twig', $retarr);
    }

    /**
     *
     * @Route("/confirm/{id}", name="uf_confirm_job", methods={"POST"})
     */
    public function confirmJobAction(Request $request, Job $job)
    {
        if (!$token = $request->request->get('_csrf_token')) {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
        }
        if (!$this->isCsrfTokenValid('confirm-job', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        // From the part this called, the previous state *shall* be ASSIGNED.
        // Just check it.
        if ($job->getState() != 'ASSIGNED')
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);

        if ($job->getPerson() !== $this->getUser())
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        
        $job->setState('CONFIRMED');
        $em = $this->getDoctrine()->getManager();
        $em->persist($job);
        $em->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    /**
     *
     * @Route("/signup/{id}", name="uf_signup_shift", methods={"POST"})
     */
    public function signupShiftAction(Request $request, Shift $shift)
    {
        $json_data = json_decode($request->getContent(), true);
        if (!$token = $request->request->get('_csrf_token')) {
            $token = $json_data['_csrf_token'];
        }

        if (!$this->isCsrfTokenValid('signup-shift', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        $job = new Job();
        $job->setShift($shift);
        $job->setPerson($user);
        $job->setState('INTERESTED');

        if (!$comment = $request->request->get('comment')) {
            $comment = $json_data['comment'] ?? null;
        }
        if ($comment) {
            $job->addNote([
                'body' => $comment,
                'type' => 'JobComment',
            ]);
        }
        if (!$checked_checks = $request->request->get('checks')) {
            $checked_checks = $json_data['checks'] ?? array();
        }
        $jobservice = $this->container->get('crewcall.jobs');
        $shift_checks = $jobservice->checksForShift($job->getShift());
        foreach ($shift_checks as $check) {
            if (!isset($checked_checks[$check['id']]))
                continue;
            $job->addNote([
                'body' => $check['body'],
                'type' => $check['type'],
                'in_reply_to' => $check['id'],
                'state' => 'CHECKED'
            ]);
        }
        $em = $this->getDoctrine()->getManager();
        $em->persist($job);
        $em->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    /**
     *
     * @Route("/delete_interest/{id}", name="uf_delete_interest", methods={"DELETE", "POST"})
     */
    public function deleteInterestAction(Request $request, Job $job)
    {
        if (!$token = $request->request->get('_csrf_token')) {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
        }
        if (!$this->isCsrfTokenValid('delete-interest', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }
        // From the part this called, the previous state *shall* be ASSIGNED.
        // Just check it.
        $user = $this->getUser();
        if ($job->getState() != 'INTERESTED')
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);

        if ($job->getPerson() !== $user)
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);

        $em = $this->getDoctrine()->getManager();
        $em->remove($job);
        $em->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    /**
     * Lists all the users jobs as calendar events.
     * Now also personstates.
     *
     * @Route("/me_calendar", name="uf_me_calendar")
     */
    public function meCalendarAction(Request $request)
    {
        // Gotta get the time scope.
        $from = $request->get('start');
        $to = $request->get('end');

        // Load the calendar html, if it's wanted.
        if (!in_array('application/json',
                $request->getAcceptableContentTypes()))
            return $this->render('user/_calendar.html.twig', [
                'from' => $from,
                'to' => $to,
                ]);
        $user = $this->getUser();
        $calendar = $this->container->get('crewcall.calendar');
        $jobservice = $this->container->get('crewcall.jobs');

        $options = [ 'from' => $from, 'to' => $to ];
        if ($state = $request->get('state'))
            $options['state'] = $state;
        /*
         * I want to have the jobs here sorted by state so that confirmed,
         * assigned and interested come in that order per day.
         * Usually it's by start_date which is corrent, but the calendar does
         * that for us.
         */
        $confirmed = [];
        $assigned = [];
        $interested = [];
        foreach ($jobservice->jobsForPerson($user, $options) as $job) {
            if ($job->getState() == 'CONFIRMED')
                $confirmed[] = $job;
            if ($job->getState() == 'ASSIGNED')
                $assigned[] = $job;
            if ($job->getState() == 'INTERESTED')
                $interested[] = $job;
        }
        /*
         * It's useless, since FullCalendar sorts by time,
         * But in case I find a cool solution to this I'll just keep it.
         */
        $jobs = array_merge($confirmed, $assigned, $interested);

        $states = $user->getStates();

        // If the date difference exeeds a week, we want to just send the
        // summary.
        $from_t = strtotime($from);
        $to_t   = strtotime($to);
        $no_summary = $request->get('no_summary');
/*
        // 20 days and above? Summary it is.        
        if (!$no_summary && (($to_t - $from_t) > 1728000)) {
            $calitems = array_merge(
                $calendar->toFullCalendarSummary($jobs, ['person' => $user, 'event_url' => false]),
                $calendar->toFullCalendarSummary($states, ['person' => $user, 'event_url' => false])
            );
        } else {
 */
            $calitems = array_merge(
                $calendar->toFullCalendarArray($jobs, ['person' => $user, 'event_url' => false, 'ical_add_url' => true, 'with_times' => true, 'count_interested' => true]),
                $calendar->toFullCalendarArray($states, ['person' => $user, 'event_url' => false, 'ical_add_url' => true, 'with_times' => true, 'count_interested' => true])
            );
  //      }
        return new JsonResponse($calitems, Response::HTTP_OK);
    }

    /**
     *
     * @Route("/job_calendaritem/{id}", name="uf_job_calendar_item", methods={"GET"})
     */
    public function jobCaledarItemAction(Request $request, Job $job)
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
     * The time log per person.
     *
     * @Route("/me_joblog", name="uf_me_joblog", methods={"GET"})
     */
    public function jobLogAction(Request $request)
    {
        $handler = $this->get('crewcall.joblogs');
        $job = null;
        $options['summary_only'] = $request->get('summary_only');
        $options['from_date'] = $request->get('from_date');
        $options['to_date'] = $request->get('to_date');

        $person = $this->getUser();
        $logs = $handler->getJobLogsForPerson($person, $options);

        // Angularfrontent
        if (in_array('application/json', $request->getAcceptableContentTypes()))
            return new JsonResponse([
                    'jobslog' => $logs['joblog_array'],
                    'summary' => $logs['summary'],
                ], Response::HTTP_OK);

        return $this->render('user/_hours.html.twig', $logs);
    }

    /**
     * The edit yourself job log / in/out.
     *
     * @Route("/{job}/me_add_joblog", name="uf_me_add_joblog", methods={"POST"})
     */
    public function addJobLogAction(Request $request, Job $job)
    {
        $user = $this->getUser();
        if ($user !== $job->getPerson()) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }
        if (!$token = $request->request->get('_csrf_token'))
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        if (!$this->isCsrfTokenValid('addjoblog' . $job->getId(), $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }
        $em = $this->getDoctrine()->getManager();
        $joblog = new JobLog();
        $joblog->setJob($job);
        $joblog->setState("SELF-ENTERED");
        $joblog->setInTime($request->request->get('in'));
        $joblog->setOutTime($request->request->get('out'));
        $joblog->setBreakMinutes($request->request->get('break') ?? 0);
        $em->persist($joblog);

        if ($comment = $request->request->get('joblogcomment')) {
            $job->addNote([
                'body' => $comment,
                'type' => 'JobComment',
            ]);
        }

        $em->flush();

        $retarr['past'] = $em->getRepository(Job::class)
            ->findJobsForPerson($user, ['booked' => true, 'past' => true]);
        $retarr['past_count'] = count($retarr['past']);
        return $this->render('user/_past.html.twig', $retarr);
    }

    /**
     * The delete yourself job log / in/out.
     *
     * @Route("/{joblog}/me_delete_joblog", name="uf_me_delete_joblog", methods={"POST"})
     */
    public function deleteJobLogAction(Request $request, JobLog $joblog)
    {
        $user = $this->getUser();
        if (!$token = $request->request->get('_csrf_token')) {
            $json_data = json_decode($request->getContent(), true);
            $token = $json_data['_csrf_token'];
        }
        if ($joblog->getState() != "SELF-ENTERED"
            || $user !== $joblog->getJob()->getPerson()
            || !$this->isCsrfTokenValid('deletejoblog' . $joblog->getId(), $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }
        $em = $this->getDoctrine()->getManager();
        $em->remove($joblog);
        $em->flush();

        $retarr['past'] = $em->getRepository(Job::class)
            ->findJobsForPerson($user, ['booked' => true, 'past' => true]);
        $retarr['past_count'] = count($retarr['past']);
        return $this->render('user/_past.html.twig', $retarr);
    }

    /**
     * The absence log
     *
     * @Route("/me_absence", name="uf_me_absence", methods={"GET"})
     */
    public function absenceAction(Request $request)
    {
        $user = $this->getUser();
        $retarr['absence'] = [];
        foreach ($user->getStates() as $ps) {
            if ($ps->isActive()) continue;
            $retarr['absence'][] = [
                'reason' => ucfirst(strtolower($ps->getState())),
                'state' => $ps->getStateLabel(),
                'from_date' => $ps->getFromDate()->format('Y-m-d'),
                'to_date' => $ps->getToDate()->format('Y-m-d'),
            ];
        }

        // Angularfrontent
        if (in_array('application/json', $request->getAcceptableContentTypes()))
            return new JsonResponse($retarr, Response::HTTP_OK);

        return $this->render('user/_absence.html.twig', $retarr);
    }

    /**
     * Profilepicture
     *
     * @Route("/{id}/file", name="uf_file", methods={"GET"})
     */
    public function fileAction(Request $request, $id)
    {
        $sf = $this->container->get('sakonnin.files');
        $sfile = $sf->getFiles(['fileid' => $id]);
        if (!$sfile)
            return new JsonResponse([
                'ERROR'=> 'Not found'], Response::HTTP_NOT_FOUND);

        if ($sfile->getThumbnailable() && $x = $request->get('y')) {
            $y = $request->get('x') ?: $y;
            // TODO: Add access control.
            // Gotta get the thumbnail then.
            $thumbfile = $sf->getThumbnailFilename($sfile, $x, $y);
            $response = new BinaryFileResponse($thumbfile);
        } else {
            $filename = $sf->getStoredFileName($sfile);
            $response = new BinaryFileResponse($filename);
        }
        return $response;
    }

    /**
     * Everything, and maybe more.
     *
     * To be honest, probably less.
     *
     * @Route("/me_files", name="uf_me_files", methods={"GET"})
     */
    public function meFiles(Request $request)
    {
        $user = $this->getUser();
        $sakonnin_files = $this->container->get('sakonnin.files');
        $addressing = $this->container->get('crewcall.addressing');
        $sfiles = $sakonnin_files->getFilesForContext([
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $user->getId()
            ]);
        $fileslist = [];
        foreach($sfiles as $sfile) {
            $f = [];
            $router = $this->container->get('router');
            $f['url'] = $router->generate('uf_file', [
                'id' => $sfile->getFileId()]);
            $f['name'] = $sfile->getName();
            $f['file_type'] = $sfile->getFileType();
            $f['description'] = $sfile->getDescription() ?: "None";
            $fileslist[] = $f;
        }
        if (in_array('application/json', $request->getAcceptableContentTypes()))
            return new JsonResponse([
                    'files' => $fileslist,
                ], Response::HTTP_OK);

        return $this->render('user/_files.html.twig', ['files' => $fileslist]);
    }

    /**
     * Edit myself.
     *
     * @Route("/edit_myself", name="uf_me_edit_myself", methods={"GET", "POST"})
     */
    public function meEditMyselfAction(Request $request)
    {
        $user = $this->getUser();

        $addressing = $this->container->get('crewcall.addressing');
        $addressing_config = $this->container->getParameter('addressing');
        $address_elements = $addressing->getFormElementList($user);
        $personfields = $this->container->getParameter('personfields');

        $form = $this->createForm(EditMyselfType::class, $user, [
               'addressing_config' => $addressing_config,
               'address_elements' => $address_elements,
               'personfields' => $personfields,
            ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('uf_me_profile');
        } else {
            return $this->render('/user/_edit.html.twig', [
                'user' => $user,
                'form' => $form->createView(),
            ]);
        }
    }

    /**
     * Change password on self.
     *
     * @Route("/change_password", name="uf_me_change_password", methods={"GET", "POST"})
     */
    public function meChangePasswordAction(Request $request, UserPasswordHasherInterface $userPasswordHasher)
    {
        $user = $this->getUser();

        // Let's see if the fos-hack-version works out here aswell.
        // $form = $this->createForm(ChangePasswordType::class);
        $form = $this->createForm(ChangePasswordFosType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encodedPassword = $userPasswordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('uf_me_profile');
        } else {
            return $this->render('/user/_password.html.twig', [
                'user' => $user,
                'form' => $form->createView(),
            ]);
        }
    }


    /**
     * Helpers
     */
    public function jobsForPersonAsArray(Person $person, $options = array())
    {
        $em = $this->getDoctrine()->getManager();
        $ccjobs = $this->container->get('crewcall.jobs');

        $jobs = $em->getRepository(Job::class)
            ->findJobsForPerson($person, $options);

        // Just walk through it once, alas overlap check here aswell.
        $lastjob = null;
        $lastarr = null;
        $checked = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($jobs as $job) {
            $arr = [
                'name' => (string)$job,
                'state' => $job->getState(),
                'id' => $job->getId(),
            ];
            $shiftarr = $this->getShiftArr($job->getShift());
            $arr = array_merge($arr, $shiftarr);

            if ($lastjob && $ccjobs->shiftOverlaps($job->getShift(), $lastjob->getShift())) {
                $arr['overlap'] = true;
                $checked->last()['overlap'] = true;
            } else {
                $arr['overlap'] = false;
            }
            $checked->add($arr);
            $lastjob = $job;
        }
        return $checked->toArray();
    }

    public function opportunitiesForPersonAsArray(Person $person, $options = array())
    {
        $em = $this->getDoctrine()->getManager();
        $ccjobs = $this->container->get('crewcall.jobs');

        $opps = [];
        $opportunities = $ccjobs->opportunitiesForPerson($person, $options);

        foreach ($ccjobs->opportunitiesForPerson($person, $options) as $o) {
            $arr = [
                'name' => (string)$o,
                // Kinda cheating.
                'state' => 'OPPORTUNITY',
                'id' => $o->getId(),
            ];
            if ($options['no_shift_data'] ?? false)
                $opps[] = $arr;
            else
                $opps[] = array_merge($arr, $this->getShiftArr($o));
        }
        return $opps;
    }

    public function getShiftArr(Shift $shift)
    {
        $sakonnin = $this->container->get('sakonnin.messages');
        // TODO: Eventcache

        // So, what do we need here? To be continued..
        if (!isset($this->shiftcache[$shift->getId()])) {
            $event = $shift->getEvent();
            $eventparent = $event->getParent();
            $location = $shift->getLocation();
            $organization = $event->getOrganization();
            $inform_notes = [];
            $checks = [];

            $scontext = [
                'system' => 'crewcall',
                'object_name' => 'shift',
                'external_id' => $shift->getId(),
            ];
            foreach ($shift->getNotes() as $note) {
                $note_id = $note['id'];
                $type = $note['type'];
                $subject = $note['subject'];
                $body = $note['body'];
                if (in_array($type, ['InformNote'])) {
                    $inform_notes[] = [
                        'id' => $note_id,
                        'subject' => $subject,
                        'confirm_required' => false,
                        'body' => $body
                    ];
                } elseif (in_array($type, ['ConfirmCheck', 'InformCheck'])) {
                    $checks[] = [
                        'id' => $note_id,
                        'type' => $type,
                        'confirm_required' => $type == "ConfirmCheck" ? true : false,
                        'body' => $body,
                        ];
                }
            }

            $eventarr = $this->getEventArr($event);
            if (count($eventarr['checks']) > 0) {
                $checks = array_merge($checks, $eventarr['checks']);
            }
            if (count($eventarr['inform_notes']) > 0) {
                $inform_notes = array_merge($inform_notes, $eventarr['inform_notes']);
            }
            unset($eventarr['checks']);
            unset($eventarr['inform_notes']);

            // Let's create some dates.
            // TODO: Ponder if this is useable. (it is, but this is eenglish
            // only for now, so limited useage.
            // $locale = $request->getLocale();
            $starttime = $shift->getStart()->getTimestamp();
            $startdaynum = $shift->getStart()->format('j');
            $startstring = $shift->getStart()->format('D H:i');
            $starttimedate = $shift->getStart()->format('D d M H:i');
            $enddaynum = $shift->getEnd()->format('j');
            $month = $shift->getStart()->format("F");

            $endstring = $shift->getEnd()->format('D H:i');
            // But...
            if ($startdaynum == $enddaynum)
                $endstring = $shift->getEnd()->format('H:i');

            $shiftarr = [
                'event' => $eventarr,
                'location' => [
                    'name' => (string)$location,
                    'description' => $location->getDescription(),
                ],
                'shift' => [
                    'name' => (string)$shift,
                    'id' => $shift->getId(),
                    'function' => (string)$shift->getFunction(),
                    'starttimestamp' => $starttime,
                    'startdaynum' => $startdaynum,
                    'start_date' => $shift->getStart()->format("Y-m-d H:i"),
                    'start_string' => $startstring,
                    'starttimedate' => $starttimedate,
                    'month' => $month,
                    'end_date' => $shift->getEnd()->format("Y-m-d H:i"),
                    'end_string' => $endstring,
                ],
                'checks' => $checks,
                'inform_notes' => $inform_notes
            ];
            if ($address = $location->getAddress()) {
                $addressing = $this->container->get('crewcall.addressing');
                $shiftarr['location']['address'] = $addressing->compose($address);
                $shiftarr['location']['address_flat'] = $addressing->compose($address, 'flat');
                $shiftarr['location']['address_string'] = $addressing->compose($address, 'string');
            }
            $this->shiftcache[$shift->getId()] = $shiftarr;
        }
        return $this->shiftcache[$shift->getId()];
    }

    public function getEventArr(Event $event)
    {
        $sakonnin = $this->container->get('sakonnin.messages');

        // So, what do we need here? To be continued..
        if (!isset($this->eventcache[$event->getId()])) {
            $eventparent = $event->getParent();
            $location = $event->getLocation();
            $organization = $event->getOrganization();
            $contacts = [];
            $contact_info = [];
            $inform_notes = [];
            $checks = [];
            $all_events = [$event];
            if ($eventparent) {
                $all_events[] = $eventparent;
            }
            foreach ($all_events as $e) {
                foreach ($e->getNotes() as $note) {
                    $note_id = $note['id'];
                    $type = $note['type'];
                    $subject = $note['subject'];
                    $body = $note['body'];
                    if (in_array($type, ['InformNote'])) {
                        $inform_notes[] = [
                            'id' => $note_id,
                            'subject' => $subject,
                            'confirm_required' => false,
                            'body' => $body
                        ];
                    } elseif (in_array($type, ['Contact Info'])) {
                        $contact_info[] = [
                            'id' => $note_id,
                            'subject' => $subject,
                            'confirm_required' => false,
                            'body' => $note['body'],
                        ];
                        $contacts[] = [
                            'name' => $note['body'],
                            'mobile_phone_number' => ''
                        ];
                    } elseif (in_array($type, ['ConfirmCheck', 'InformCheck'])) {
                        $checks[] = [
                            'id' => $note_id,
                            'type' => $type,
                            'confirm_required' => $type == "ConfirmCheck" ? true : false,
                            'body' => $body,
                            ];
                    }
                }
            }

            if ((!$event_description = $event->getDescription()) && $event->getParent())
                $event_description = $event->getParent()->getDescription();

            $eventarr = [
                'name' => (string)$event,
                'id' => $event->getId(),
                'parent_id' => $event->getParent() ? $event->getParent()->getId() : null,
                'description' => $event_description,
                'organization' => [
                    'name' => $organization->getName(),
                ],
                'contacts' => [],
                'checks' => $checks,
                'contact_info' => $contact_info,
                'inform_notes' => $inform_notes
            ];
            $this->eventcache[$event->getId()] = $eventarr;
        }
        return $this->eventcache[$event->getId()];
    }
}
