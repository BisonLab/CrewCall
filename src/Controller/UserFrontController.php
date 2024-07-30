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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;
use BisonLab\SakonninBundle\Service\Messages as SakonninMessages;
use BisonLab\SakonninBundle\Service\Files as SakonninFiles;

use App\Entity\Person;
use App\Entity\PersonFunction;
use App\Entity\Event;
use App\Entity\Shift;
use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\FunctionEntity;
use App\Form\ChangePasswordFosType;
use App\Form\ChangePasswordFormType;
use App\Form\EditMyselfType;
use App\Model\FullCalendarEvent;
use App\Service\JobsLogs;
use App\Service\Jobs as CcJobs;
use App\Service\AttributeFormer;
use App\Service\Calendar as CcCalendar;
use App\Service\Addressing;

/**
 * User controller.
 * This is the controller for the front end par of the application.
 *
 * It's the only one for now, and may be pushed onto it's own bundle in case
 * someone means we need different front ends.
 *
 * Like an App for some odd reason.
 */
#[Route(path: '/uf')]
class UserFrontController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
        private ParameterBagInterface $parameterBag,
        private Addressing $addressing,
        private CcJobs $ccJobs,
        private CcCalendar $ccCalendar,
        private AttributeFormer $attributeFormer,
        private RouterInterface $router,
    ) {
    }

    private $shiftcache = [];
    private $eventcache = [];

    /**
     * Ping
     */
    #[Route(path: '/ping', name: 'uf_ping', methods: ['GET'])]
    public function pingAction(Request $request)
    {
        return new JsonResponse([
            'ACK' => true,
            ],
            Response::HTTP_OK);
    }

    /**
     * Everything, and maybe more.
     */
    #[Route(path: '/me', name: 'uf_me', methods: ['GET'])]
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
        $retarr = $this->meJobs($request, true);
        $retarr['notes'] = $this->meNotes($request, true);
        $retarr['messages'] = $this->meMessages($request, true);
        $retarr['user'] = $user;
        return $this->render('user/_home.html.twig', $retarr);
    }

    /**
     * Everything, and maybe more.
     */
    #[Route(path: '/me_profile', name: 'uf_me_profile', methods: ['GET'])]
    public function meProfileAction(Request $request)
    {
        $user = $this->getUser();
        $pfiles = $this->sakonninFiles->getFilesForContext([
                'file_type' => 'ProfilePicture',
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $user->getId()
            ]);
        $profile_picture_url = null;
        if (count($pfiles) > 0) {
            $profile_picture_url = $this->router->generate('uf_file', [
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
            $retarr['address'] = $this->addressing->compose($address);
            $retarr['address_flat'] = $this->addressing->compose($address, 'flat');
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
     */
    #[Route(path: '/me_notes', name: 'uf_me_notes', methods: ['GET'])]
    public function meNotes(Request $request, $as_array = false)
    {
        $archive = $request->get('archive');
        $states = ['UNREAD', 'SENT', 'SHOW'];
        if ($archive)
            $states = ['ARCHIVED'];
        
        $user = $this->getUser();
        $pncontext = [
            'system' => 'crewcall',
            'object_name' => 'person',
            'message_type' => 'PersonNote',
            'states' => $states,
            'external_id' => $user->getId(),
        ];
        $pnotes = [];
        foreach ($this->sakonninMessages->getMessagesForContext($pncontext) as $m) {
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
        foreach ($this->sakonninMessages->getMessages($criterias) as $m) {
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
     */
    #[Route(path: '/me_messages', name: 'uf_me_messages', methods: ['GET'])]
    public function meMessages(Request $request, $as_array = false)
    {
        $user = $this->getUser();
        $pmessages = [];

        foreach ($this->sakonninMessages->getMessagesForUser($user, ['state' => 'UNREAD']) as $m) {
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
     * Jobs part, and how!
     *
     * This is big, huge and does a lot.
     *
     * But I need to send quite a few numbers at the same time since we have a
     * few bubbles needing updates.
     * I'll try to filter out the cases when it's not like that to speed it all
     * up a bit.
     */
    #[Route(path: '/me_jobs', name: 'uf_me_jobs', methods: ['GET'])]
    public function meJobs(Request $request, CsrfTokenManagerInterface $csrfman, $as_array = false)
    {
        // Create a csrf token for use in the next step
        $view = $request->get('view') ?? null;
        if ($view && !in_array($view, ['past', 'jobs_list', 'opportunities', 'interested', 'uninterested', 'assigned', 'confirmed']))
            throw new \InvalidArgumentException("Funnily enough, I do not acceept your view.");

        $user = $this->getUser();
        $today = new \DateTime();
        $from = new \DateTime($request->get('from') ?? null);
        $to = new \DateTime($request->get('to') ?? '+1 year');
        $state = $request->get('state') ?? null;

        // Do not make the counter at the bottom base itself on just that month.
        $recount = true;
        if ($month = $request->get('month'))
            $recount = false;

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
            'uninterested' => null,
            'uninterested_count' => 0,
            'assigned' => null,
            'assigned_count' => 0,
            'confirmed' => null,
            'confirmed_count' => 0
            ];

        /*
         * Not that I need these all the time, but they cost nothing.
         * I guess I can consider creating these on the fly now. but is that
         * effective?
         */
        $signuptoken = $csrfman->getToken('signup-shift')->getValue();
        $retarr['signup_shift'] = [
            '_csrf_token' => $signuptoken,
            'url' => $this->generateUrl('uf_signup_shift', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];
        $retarr['set_uninterested'] = [
            '_csrf_token' => $signuptoken,
            'url' => $this->generateUrl('uf_uninterested', ['id' => 'ID'], UrlGeneratorInterface::ABSOLUTE_URL)
        ];
            
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

        /*
         * The defaults.
         */
        if (!$view || in_array($view, ['opportunities', 'interested', 'assigned', 'confirmed'])) {
            $retarr['opportunities'] = $this->opportunitiesForPersonAsArray(
                $user,
                [ 'from' => $from, 'to' => $to, 'no_shift_data' => $as_array ]
                );
            $retarr['opportunities_count'] = count($retarr['opportunities']);

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
        }

        if ($view && in_array($view, ['jobs_list'])) {
            $retarr['opportunities'] = $this->opportunitiesForPersonAsArray(
                $user,
                [ 'from' => $from, 'to' => $to, 'no_shift_data' => $as_array ]
                );
            $retarr['opportunities_count'] = count($retarr['opportunities']);

            $retarr['interested'] = $this->jobsForPersonAsArray($user, [
                'from' => $from, 'to' => $to,
                'state' => 'INTERESTED']);
            $retarr['interested_count'] = count($retarr['interested']);
        }

        /*
         * No need to count and check these unless it's specified.
         */
        if ($view && in_array($view, ['uninterested'])) {
            $retarr['uninterested'] = $this->jobsForPersonAsArray($user, [
                'to' => $to,
                'state' => 'UNINTERESTED']);
            $retarr['uninterested_count'] = count($retarr['uninterested']);
        }

        /*
         * Past and we're talking back in time, and then modify from/to
         * with a year.
         * Unless it's this month.
         */
        if ($view && in_array($view, ['past'])) {
            if ($month && $from->format('m') != date('m')) {
                $from = $from->modify("-1 year");
                $to = $to->modify("-1 year");
                $retarr['period'] = [ 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d') ];
            } else {
                $from = new \DateTime('first day of last year');
                $to = new \DateTime();
                $retarr['period'] = [ 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d') ];
            }
            $retarr['past'] = $this->entityManager->getRepository(Job::class)
                ->findJobsForPerson($user, [
                    'from' => $from,
                    'to' => $to,
                    'booked' => true, 'past' => true]);
            $retarr['past_count'] = count($retarr['past']);
        }

        if ($as_array)
            return $retarr;
        if (!$view || in_array('application/json',
                $request->getAcceptableContentTypes()))
            return new JsonResponse($retarr, 200);

        return $this->render('user/_' . $view . '.html.twig', $retarr);
    }

    
    #[Route(path: '/confirm/{id}', name: 'uf_confirm_job', methods: ['POST'])]
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
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    
    #[Route(path: '/signup/{id}', name: 'uf_signup_shift', methods: ['POST'])]
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
        $shift_checks = $this->CcJobs->checksForShift($job->getShift());
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
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    
    #[Route(path: '/uninterested/{id}', name: 'uf_uninterested', methods: ['POST'])]
    public function uninterestedAction(Request $request, Shift $shift)
    {
        $json_data = json_decode($request->getContent(), true);
        if (!$token = $request->request->get('_csrf_token')) {
            $token = $json_data['_csrf_token'];
        }

        // Using the same as above.
        if (!$this->isCsrfTokenValid('signup-shift', $token)) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        $job = new Job();
        $job->setShift($shift);
        $job->setPerson($user);
        $job->setState('UNINTERESTED');
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    /**
     *
     * Now also deleting uninterested.
     */
    #[Route(path: '/delete_interest/{id}', name: 'uf_delete_interest', methods: ['DELETE', 'POST'])]
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
        if (!in_array($job->getState(), ['UNINTERESTED', 'INTERESTED']))
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);

        if ($job->getPerson() !== $user)
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);

        $this->entityManager->remove($job);
        $this->entityManager->flush($job);
        return new JsonResponse(["OK" => "Well done"], Response::HTTP_OK);
    }

    /**
     * Lists all the users jobs as calendar events.
     * Now also personstates.
     */
    #[Route(path: '/me_calendar', name: 'uf_me_calendar')]
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
        foreach ($this->CcJobs->jobsForPerson($user, $options) as $job) {
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
                $this->ccCalendar->toFullCalendarSummary($jobs, ['person' => $user, 'event_url' => false]),
                $this->ccCalendar->toFullCalendarSummary($states, ['person' => $user, 'event_url' => false])
            );
        } else {
 */
            $calitems = array_merge(
                $this->ccCalendar->toFullCalendarArray($jobs, ['person' => $user, 'event_url' => false, 'ical_add_url' => true, 'with_times' => true, 'count_interested' => true]),
                $this->ccCalendar->toFullCalendarArray($states, ['person' => $user, 'event_url' => false, 'ical_add_url' => true, 'with_times' => true, 'count_interested' => true])
            );
  //      }
        return new JsonResponse($calitems, Response::HTTP_OK);
    }

    
    #[Route(path: '/job_calendaritem/{id}', name: 'uf_job_calendar_item', methods: ['GET'])]
    public function jobCaledarItemAction(Request $request, Job $job)
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

    /**
     * The time log per person.
     */
    #[Route(path: '/me_joblog', name: 'uf_me_joblog', methods: ['GET'])]
    public function jobLogAction(Request $request, JobLogs $joblogs)
    {
        $job = null;
        $options['summary_only'] = $request->get('summary_only');
        $options['from_date'] = $request->get('from_date');
        $options['to_date'] = $request->get('to_date');

        $person = $this->getUser();
        $logs = $joblogs->getJobLogsForPerson($person, $options);

        // Angularfrontent
        if (in_array('application/json', $request->getAcceptableContentTypes()))
            return new JsonResponse([
                    'jobslog' => $logs['joblog_array'],
                    'summary' => $logs['summary'],
                ], Response::HTTP_OK);

        return $this->render('user/_hours.html.twig', $logs);
    }

    /**
     * The edit yourself job log / in/out form.
     */
    #[Route(path: '/{job}/me_new_joblog', name: 'uf_me_new_joblog', methods: ['GET'])]
    public function newJobLogAction(Request $request, Job $job)
    {
        $user = $this->getUser();
        if ($user !== $job->getPerson()) {
            return new JsonResponse(["ERRROR" => "No luck"], Response::HTTP_FORBIDDEN);
        }
        // Looking stupid? Kinda.
        $retarr = ['job' => $job, 'month' => $request->get('month')];
        return $this->render('user/_new_joblog_form.html.twig', $retarr);
    }

    /**
     * The edit yourself job log / in/out.
     */
    #[Route(path: '/{job}/me_create_joblog', name: 'uf_me_create_joblog', methods: ['POST'])]
    public function createJobLogAction(Request $request, Job $job)
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
        $this->entityManager->persist($joblog);

        if ($comment = $request->request->get('joblogcomment')) {
            $job->addNote([
                'body' => $comment,
                'type' => 'JobComment',
            ]);
        }

        $this->entityManager->flush();
        return new Response("Added", Response::HTTP_CREATED);
    }

    /**
     * The delete yourself job log / in/out.
     */
    #[Route(path: '/{joblog}/me_delete_joblog', name: 'uf_me_delete_joblog', methods: ['POST', 'DELETE'])]
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
        $this->entityManager->remove($joblog);
        $this->entityManager->flush();
        return new Response("Deleted", Response::HTTP_OK);
    }

    /**
     * The absence log
     */
    #[Route(path: '/me_absence', name: 'uf_me_absence', methods: ['GET'])]
    public function absenceAction(Request $request)
    {
        $user = $this->getUser();
        $params['absence'] = [];
        foreach ($user->getStates() as $ps) {
            // if ($ps->isActive()) continue;
            $from_stamp = $ps->getFromDate()->format('U');
            $params['absence'][$from_stamp] = [
                'type' => 'person_state',
                'state' => $ps->getStateLabel(),
                'from_stamp' => $from_stamp,
                'from_date' => $ps->getFromDate()->format('Y-m-d'),
                'to_date' => $ps->getToDate()?->format('Y-m-d'),
            ];
        }
        foreach ($user->getJobs(['noshow' => true]) as $job) {
            $from_stamp = $ps->getFromDate()->format('U');
            $params['absence'][$from_stamp] = [
                'type' => 'job_state',
                'state' => $job->getStateLabel() . " as " . (string)$job->getShift(),
                'from_stamp' => $from_stamp,
                'from_date' => $job->getStart()->format('Y-m-d'),
                'to_date' => $job->getEnd()?->format('Y-m-d'),
            ];
        }

        // Angularfrontend
        if (in_array('application/json', $request->getAcceptableContentTypes()))
            return new JsonResponse($params, Response::HTTP_OK);

        return $this->render('user/_absence.html.twig', $params);
    }

    /**
     * Profilepicture
     */
    #[Route(path: '/{id}/file', name: 'uf_file', methods: ['GET'])]
    public function fileAction(Request $request, $id)
    {
        $sfile = $this->sakonninFiles->getFiles(['fileid' => $id]);
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
     */
    #[Route(path: '/me_files', name: 'uf_me_files', methods: ['GET'])]
    public function meFiles(Request $request)
    {
        $user = $this->getUser();
        $sfiles = $sakonnin_files->getFilesForContext([
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $user->getId()
            ]);
        $fileslist = [];
        foreach($sfiles as $sfile) {
            $f = [];
            $f['url'] = $this->router->generate('uf_file', [
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
     */
    #[Route(path: '/edit_myself', name: 'uf_me_edit_myself', methods: ['GET', 'POST'])]
    public function meEditMyselfAction(Request $request)
    {
        $user = $this->getUser();
        $addressing_config = $this->parameterBag->get('addressing');
        $address_elements = $this->addressing->getFormElementList($user);
        $personfields = $this->parameterBag->get('personfields');

        $form = $this->createForm(EditMyselfType::class, $user, [
               'addressing_config' => $addressing_config,
               'address_elements' => $address_elements,
               'personfields' => $personfields,
            ]);

        $feRepo = $this->entityManager->getRepository(FunctionEntity::class);
        $pickable_functions = $feRepo->findPickableFunctions();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // We can only update what we are supposed to update. I do not
            // want any trickery.
            $attributes_forms = $this->attributeFormer->getForms($user);
            foreach ($attributes_forms as $aform) {
                $aform->handleRequest($request);
                $aformdata = $aform->getData();
                foreach ($aformdata as $key => $val) {
                    if (!isset($personfields[$key])) continue;
                    if ($personfields[$key]['user_editable'] ?? false)
                        $user->setAttribute($key, $val);
                }
            }
            if (count($pickable_functions) > 0) {
                $functions = $request->request->get('functions') ?? [];
                $pfs = array();
                foreach ($user->getPersonFunctions() as $pf) {
                    // Should we care?
                    if (!$pickable_functions->contains($pf->getFunction()))
                        continue;
                    if (!in_array($pf->getFunctionId(), $functions)) {
                        $this->entityManager->remove($pf);
                    }
                    $pfs[] = $pf->getFunctionId();
                }
                foreach ($functions as $hf) {
                    if (!in_array($hf, $pfs)) {
                        $function = $this->entityManager->getRepository(FunctionEntity::class)->find($hf);
                        $pf = new PersonFunction();
                        $pf->setFunction($function);
                        $pf->setFromDate(new \DateTime());
                        $user->addPersonFunction($pf);
                        $this->entityManager->persist($pf);
                    }
                }
            }
            $this->entityManager->flush();
            return $this->redirectToRoute('uf_me_profile');
        }
        $attributes_forms = $this->attributeFormer->getEditForms($user);
        // Gotta filter out the fields not user editable.
        $my_attributes_forms = [];
        foreach ($attributes_forms as $aform) {
            foreach ($aform as $key => $widget) {
                if (!isset($personfields[$key])) continue;
                if ($personfields[$key]['user_editable'] ?? false)
                    $my_attributes_forms[] = $widget;
            }
        }
        return $this->render('/user/_edit.html.twig', [
            'user' => $user,
            'pickable_functions' => $pickable_functions,
            'my_attributes_forms' => $my_attributes_forms,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Change password on self.
     */
    #[Route(path: '/change_password', name: 'uf_me_change_password', methods: ['GET', 'POST'])]
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

            $this->entityManager->flush();

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
        $jobs = $this->entityManager->getRepository(Job::class)
            ->findJobsForPerson($person, $options);

        /*
         * OK; this looks stupid, but I gotta do this somehow.
         * I have no need at all for old UNINTERESTED jobs.
         * Alas, I'll just delete them.
         */
        $now = new \DateTime();
        // Just walk through it once, alas overlap check here aswell.
        $lastjob = null;
        $lastarr = null;
        $checked = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($jobs as $job) {
            // As I said, just get rid.
            if ($job->getStart() == "UNINTERESTED"
                && $job->getStart() < $now) {
                $this->entityManager->remove($job);
                continue;
            }

            $arr = [
                'name' => (string)$job,
                'state' => $job->getState(),
                'id' => $job->getId(),
            ];
            $shiftarr = $this->getShiftArr($job->getShift());
            $arr = array_merge($arr, $shiftarr);

            if ($lastjob && $this->ccJobs->shiftOverlaps($job->getShift(), $lastjob->getShift())) {
                $arr['overlap'] = true;
                $checked->last()['overlap'] = true;
            } else {
                $arr['overlap'] = false;
            }
            $checked->add($arr);
            $lastjob = $job;
        }
        // And make them no longer useful UNINTERESTED go.
        $this->entityManager->flush();
        return $checked->toArray();
    }

    /*
     * This basically returns "fake jobs". There is no job created in the
     * database, just compiled for this occation.
     */
    public function opportunitiesForPersonAsArray(Person $person, $options = array())
    {
        $opps = [];
        $opportunities = $this->ccJobs->opportunitiesForPerson($person, $options);

        foreach ($this->ccJobs->opportunitiesForPerson($person, $options) as $o) {
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
                    'maplink' => $location->getMapLink(),
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
                $shiftarr['location']['address'] = $this->addressing->compose($address);
                $shiftarr['location']['address_flat'] = $this->addressing->compose($address, 'flat');
                $shiftarr['location']['address_string'] = $this->addressing->compose($address, 'string');
            }
            $this->shiftcache[$shift->getId()] = $shiftarr;
        }
        return $this->shiftcache[$shift->getId()];
    }

    public function getEventArr(Event $event)
    {
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
