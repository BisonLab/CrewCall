<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Person;
use App\Entity\PersonState;
use App\Entity\PersonFunction;
use App\Entity\PersonRoleOrganization;
use App\Entity\FunctionEntity;
use App\Lib\ExternalEntityConfig;
use App\Form\ChangePasswordFosType;
use App\Form\ResetPasswordRequestFormType;

/**
 * Person controller.
 *
 * @Route("/admin/{access}/person", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class PersonController extends CommonController
{
    use CommonControllerFunctions;
    /**
     * Lists absolutely all person entities.
     *
     * @Route("/", name="person_index", methods={"GET"})
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $people = $em->getRepository('App:Person')->findAll();
        $fe_repo = $em->getRepository('App:FunctionEntity');

        $functions = $fe_repo->findAll();
        return $this->render('person/index.html.twig', array(
            'people' => $people,
            'functions' => $functions,
            'simplified' => false,
            'functionEntity' => null
        ));
    }

    /**
     * Can become a new controller, but keep it here for now.
     *
     * @Route("/crew", name="crew_index", methods={"GET"})
     */
    public function crewIndexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $fe_repo = $em->getRepository('App:FunctionEntity');
        $job_repo = $em->getRepository('App:Job');

        $select_grouping = $request->get('select_grouping');
        $simplified = $request->get('simplified');
        $on_date = $request->get('on_date');

        $people = [];
        $functionEntity = null;
        if ($fid = $request->get('function_id')) {
            if (!$functionEntity = $fe_repo->find($fid))
                return $this->returnNotFound($request, 'No function to filter');

            if ($select_grouping == 'all') {
                $people = $functionEntity->getPeople(false);
            } else {
                $people = $this->filterPeople($functionEntity->getPeople(false), [
                    'crew_only' => true,
                    'select_grouping' => $select_grouping,
                    'on_date' => $on_date,
                ]);
            }
        } else {
                $people = $this->filterPeople($em->getRepository('App:Person')->findAll(),[
                    'crew_only' => true,
                    'select_grouping' => $select_grouping,
                    'on_date' => $on_date,
                ]);
        }

        $functions = $fe_repo->findAllActive();
        return $this->render('person/crewindex.html.twig', array(
            'people' => $people,
            'on_date' => $on_date,
            'simplified' => $simplified,
            'select_grouping' => $select_grouping,
            'functions' => $functions,
            'functionEntity' => $functionEntity,
        ));
    }

    /**
     * Lists all person entities with a function
     *
     * @Route("/function", name="person_function", methods={"GET"})
     */
    public function listByFunctionAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $fe_repo = $em->getRepository('App:FunctionEntity');
        $job_repo = $em->getRepository('App:Job');

        $fid = $request->get('function_id');
        $select_grouping = $request->get('select_grouping');
        $on_date = $request->get('on_date') ?? null;
        if (!$functionEntity = $fe_repo->find($fid))
            return $this->returnNotFound($request, 'No function to filter');

        if ($select_grouping == 'all') {
            $people = $functionEntity->getPeople(false);
        } else {
            $people = $this->filterPeople($functionEntity->getPeople(false), [
                'select_grouping' => $select_grouping,
                'on_date' => $on_date,
            ]);
        }

        $functions = $fe_repo->findAllActive();
        return $this->render('person/index.html.twig', array(
            'people' => $people,
            'simplified' => false,
            'on_date' => $on_date,
            'functions' => $functions,
            'functionEntity' => $functionEntity,
        ));
    }

    /**
     * Lists all person entities with a Rolj
     *
     * @Route("/role", name="person_role", methods={"GET"})
     */
    public function listByRoleAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $role_repo = $em->getRepository('App:Role');
        $person_repo = $em->getRepository('App:Person');

        $on_date = $request->get('on_date');
        $role = null;
        $people = [];
        if ($rid = $request->get('role_id')) {
            if (!$role = $role_repo->find($rid))
                return $this->returnNotFound($request, 'No role to filter');
            $people = $role->getPeople();
        } else {
            $people = $person_repo->findWithRoles();
        }

        if ($select_grouping = $request->get('select_grouping')) {
            $people = $this->filterPeople($people, [
                'select_grouping' => $select_grouping,
                'on_date' => null,
            ]);
        }

        return $this->render('person/roleindex.html.twig', array(
            'people' => $people,
            'role' => $role,
            'roles' => $role_repo->findAll(),
        ));
    }

    /**
     *
     * @Route("/applicants", name="person_applicants", methods={"GET"})
     */
    public function listApplicantsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $people = $em->getRepository('App:Person')->findByState('APPLICANT');

        return $this->render('person/applicants.html.twig', array(
            'applicants' => $people));
    }

    /**
     * Creates a new person entity.
     * This is only used when you add a crewmember. People with roles
     * will be created via the Organization or Location controller.
     *
     * @Route("/new_crewmember", name="person_new_crewmember", methods={"GET", "POST"})
     */
    public function newCrewmemberAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $person = new Person();
        $person->setSystemRole('ROLE_USER');
        $addressing_config = $this->container->getParameter('addressing');
        $addressing = $this->container->get('crewcall.addressing');
        $address_elements = $addressing->getFormElementList($person);
        $internal_organization_config = $this->container->getParameter('internal_organization');
        $first_org = $em->getRepository('App:Organization')->getInternalOrganization();
        $first_role = $em->getRepository('App:Role')->getDefaultRole();

        $form = $this->createForm('App\Form\NewPersonType',
            $person, [
               'addressing_config' => $addressing_config,
               'address_elements' => $address_elements,
               'organization' => $first_org,
               'role' => $first_role,
               'internal_organization_config' => $internal_organization_config,
            ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $form_data = $form->getData();
            $pf = new PersonFunction();
            $pf->setPerson($person);
            $pf->setFunction($form->get('function')->getData());

            $pro = new PersonRoleOrganization();
            if ($internal_organization_config['allow_external_crew']) {
                $pro->setPerson($person);
                $pro->setOrganization($form->get('organization')->getData());
                $pro->setRole($form->get('role')->getData());
            } else {
                $pro->setPerson($person);
                $pro->setOrganization($first_org);
                $pro->setRole($first_role);
            }
            // I have removed password setting, alas I have to set something
            // until the user has reset their password.
            $person->setPassword(\ShortCode\Random::get(16));
            $em->persist($person);
            $em->persist($pf);
            $em->persist($pro);
            $em->flush($person);

            return $this->redirectToRoute('person_show', array('id' => $person->getId()));
        }

        return $this->render('person/new.html.twig', array(
            'person' => $person,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a person entity.
     *
     * @Route("/{id}/show", name="person_show", methods={"GET"})
     */
    public function showAction(Person $person)
    {
        $deleteForm = $this->createDeleteForm($person);
        $stateForm  = $this->createStateForm($person);
        $resetPasswordForm  = $this->createForm(ResetPasswordRequestFormType::class);

        return $this->render('person/show.html.twig', array(
            'person' => $person,
            'delete_form' => $deleteForm->createView(),
            'state_form' => $stateForm->createView(),
            'reset_form' => $resetPasswordForm->createView(),
        ));
    }

    /**
     * Calendar for person
     *
     * @Route("/{id}/calendar", name="person_calendar", methods={"POST"})
     */
    public function personCalendarAction(Request $request, $access, Person $person)
    {
        $calendar = $this->container->get('crewcall.calendar');
        $jobservice = $this->container->get('crewcall.jobs');

        // Gotta get the time scope.
        $from = $request->get('start');
        $to = $request->get('end');
        $jobs = $jobservice->jobsForPerson($person,
            array('all' => true, 'from' => $from, 'to' => $to));
        $states = $person->getStates();
        
        $calitems = array_merge(
            $calendar->toFullCalendarArray($jobs, $person),
            $calendar->toFullCalendarArray($states, $person)
        );
        // Not liked by OWASP since we just return an array.
        return new JsonResponse($calitems, Response::HTTP_OK);
    }

    /**
     * Displays a form to edit an existing person entity.
     *
     * @Route("/{id}/edit", name="person_edit", methods={"GET", "POST"})
     */
    public function editAction(Request $request, Person $person)
    {
        $addressing_config = $this->container->getParameter('addressing');
        $addressing = $this->container->get('crewcall.addressing');
        $address_elements = $addressing->getFormElementList($person);
        $editForm = $this->createForm('App\Form\PersonType',
            $person, [
                'addressing_config' => $addressing_config,
                'address_elements' => $address_elements
            ]);
        $editForm->remove('plainPassword');
        // $addressing->addToForm($editForm, $person);
        $editForm->handleRequest($request);

        $contexts      = $person->getContexts();
        $context_forms = $this->createContextForms('App:Person', $contexts);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->updateContextForms($request,'App:Person', "\App\Entity\\PersonContext", $person);

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('person_show', array('id' => $person->getId()));
        }

        return $this->render('person/edit.html.twig', array(
            'person' => $person,
            'edit_form' => $editForm->createView(),
            'context_forms' => $context_forms,
        ));
    }

    /**
     * Deletes a person entity.
     *
     * @Route("/{id}", name="person_delete", methods={"DELETE"})
     */
    public function deleteAction(Request $request, Person $person)
    {
        $form = $this->createDeleteForm($person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($person);
            $em->flush($person);
        }

        return $this->redirectToRoute('dashboard');
    }

    /**
     * Sends messages to a batch of persons.
     *
     * @Route("/persons_send_message", name="persons_send_message", methods={"POST"})
     */
    public function personsSendMessageAction(Request $request)
    {
        $sm = $this->get('sakonnin.messages');
        $body = $request->request->get('body');
        $subject = $request->request->get('subject') ?? "Message from CrewCall";
        $message_type = $request->request->get('message_type');

        $person_contexts = [];
        foreach ($request->request->get('person_list') as $pid) {
            $person_contexts[] = [
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $pid
            ];
        }
        if (!empty($person_contexts)) {
            $sm->postMessage(array(
                'subject' => $subject,
                'body' => $body,
                'from' => $this->container->getParameter('mailfrom'),
                'message_type' => $message_type,
                'to_type' => "INTERNAL",
                'from_type' => "INTERNAL",
            ), $person_contexts);
            $status_text = "Sent '".$body."' to " . count($person_contexts) . " persons.";
            return new Response($status_text, Response::HTTP_OK);
        }
        // It's kinda still a 200/OK
        return new Response("Did not send any  message, no one to send it to.", Response::HTTP_OK);
    }

    /**
     * Finds and displays the gedmo loggable history
     *
     * @Route("/{id}/log", name="person_log")
     */
    public function showLogAction(Request $request, $access, $id)
    {
        return  $this->showLogPage($request,$access, "App:Person", $id);
    }

    /**
     * Finds and returns the jobs for a person.
     *
     * @Route("/{id}/jobs", name="person_jobs", methods={"GET"})
     */
    public function showJobsAction(Request $request, $access, Person $person)
    {
        $options = [];
        // I'll default today +2 days. Add options at will and need.
        $options['from'] = new \DateTime();
        $options['to'] = new \DateTime('+2days');
        $summary = [];
        foreach($this->get('crewcall.jobs')->jobsForPerson(
            $person, $options) as $job) {
                $summary[] = [(string)$job, $job->getStart()->format("d M H:i"), $job->getEnd()->format("d M H:i"), $job->getState()];
        }
        
        if ($this->isRest($access)) {
            return $this->returnRestData($request, $summary,
                array('html' => 'summaryPopContent.html.twig'));
        }
    }

    /**
     * Change password on self.
     *
     * @Route("/change_password", name="self_change_password", methods={"GET", "POST"})
     */
    public function changeSelfPasswordAction(Request $request, UserPasswordHasherInterface $userPasswordHasher)
    {
        $user = $this->getUser();

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

            return $this->redirectToRoute('dashboard');
        } else {
            return $this->render('/person/change_self_password.html.twig',
                array(
                'user' => $user,
                'form' => $form->createView(),
            ));
        }
    }

    /**
     * Creates a form to delete a person entity.
     *
     * @param Person $person The person entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Person $person)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('person_delete', array('id' => $person->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * Creates a form to delete a person entity.
     *
     * @param Person $person The person entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createStateForm(Person $person)
    {
        $stateform = $this->createFormBuilder()
            ->add('from_date', DateType::class, array(
                'label' => "From",
                'format' => 'yyyy-MM-dd',
                'widget' => "single_text"))
            ->add('to_date', DateType::class, array(
                'required' => false,
                'format' => 'yyyy-MM-dd',
                'label' => "To",
                'widget' => "single_text"))
            ->add('state', ChoiceType::class, array(
                'choices' => ExternalEntityConfig::getStatesAsChoicesFor('Person')))
            ->add('submit', SubmitType::class)
            ->setAction($this->generateUrl('person_state',
                array('id' => $person->getId())))
            ->setMethod('POST')
            ->getForm();
//        $stateform->find('person_id')->setData($person->getId());

        return $stateform;
    }

    /**
     * Set state on a person.
     *
     * @Route("/{id}/state", name="person_state", methods={"POST"})
     */
    public function stateAction(Request $request, Person $person)
    {
        // Security? This is the admin area, they can mess it all up anyway.
        // If form:
        $form = $this->createStateForm($person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $form_data = $form->getData();
            $person->setState($form_data['state'], array(
                'from_date' => $form_data['from_date'] ?: null,
                'to_date' => $form_data['to_date'] ?: null,
                ));
            $em->flush();
            return $this->redirectToRoute('person_show',
                array('id' => $person->getId()));
        }
        // Hopefully from the applicants page: (Or REST)
        if (!$state = $request->request->get('state'))
            return $this->redirectToRoute('person_show',
                array('id' => $person->getId()));
        $options = array();
        if ($from_date = $request->request->get('from_date'))
            $options['from_date'] = $from_date;
        if ($to_date = $request->request->get('to_date'))
            $options['to_date'] = $to_date;
        $person->setState($state, $options);
        $this->getDoctrine()->getManager()->flush();
        $applicant = $request->request->get('applicant');
        if ($applicant)
            return $this->redirectToRoute('person_applicants');
        else
            return $this->redirectToRoute('person_show',
                array('id' => $person->getId()));
    }

    /**
     * @Route("/search", name="person_search", methods={"GET"})
     */
    public function searchPersonAction(Request $request, $access)
    {
        if (!$term = $request->query->get("term"))
            $term = $request->query->get("username");

        // Gotta be able to handle two-letter usernames.
        if (strlen($term) > 1) {
            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository("App:Person");

            $q = $repo->createQueryBuilder('u')
                ->where('lower(u.username) LIKE :term')
                ->orWhere('lower(u.email) LIKE :term')
                ->setParameter('term', strtolower($term) . '%')
                ->orWhere('lower(u.full_name) LIKE :full_name')
                ->setParameter('full_name', '%' . strtolower($term) . '%')
                ->orWhere('lower(u.mobile_phone_number) LIKE :mobile_phone_number')
                ->setParameter('mobile_phone_number', '%' . strtolower($term) . '%')
                ->orWhere('lower(u.home_phone_number) LIKE :home_phone_number')
                ->setParameter('home_phone_number', '%' . strtolower($term) . '%');

            $people = [];
            if ($users = $q->getQuery()->getResult()) {
                foreach ($users as $user) {
                    // Here comes the difference from commonbundle:
                    // Filtering here, since I already go through them.
                    if ($request->query->get("enabled")) {
                        if (!$user->getEnabled())
                            continue;
                    }
                    /*
                     * The simplest way to filter. If they have a
                     * skill/person_function they are crew.
                     */
                    if ($request->query->get("crew_only")) {
                        if (!$user->isCrew())
                            continue;
                    }

                    // TODO: Add full name.
                    $res = array(
                        'userid' => $user->getId(),
                        'value' => $user->getUserName(),
                        'email' => $user->getEmail(),
                        'label' => $user->getUserName(),
                        'username' => $user->getUserName(),
                    );
                    // Override if full name exists.
                    if (property_exists($user, 'full_name') 
                            && $user->getFullName()) {
                        $res['label'] = $user->getFullName();
                        $res['value'] = $user->getFullName();
                    }
                    // Should I somehow know if an email address is bogus
                    // (autogenerated) and just not show it?
                    if ($request->get("value_with_all")) {
                        $res['value'] = $res['value'] . " - " . $user->getMobilePhoneNumber();
                        $res['label'] = $res['label'] . " - " . $user->getMobilePhoneNumber();
                        $res['value'] = $res['value'] . " - " . $user->getEmail();
                        $res['label'] = $res['label'] . " - " . $user->getEmail();
                    }
                    $people[] = $res;
                }        
            }
        }

        if ($this->isRest($access)) {
            // Format for autocomplete.
            return $this->returnRestData($request, $people);
        }

        $people = array(
            'entities' => $people,
        );
        return $this->render('person/index.html.twig', $params);
    }
}
