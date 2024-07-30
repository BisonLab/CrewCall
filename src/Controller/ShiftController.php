<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use BisonLab\SakonninBundle\Service\Messages as SakonninMessages;

use App\Lib\ExternalEntityConfig;
use App\Entity\Shift;
use App\Entity\Event;

/**
 * Shift controller.
 */
#[Route(path: '/admin/{access}/shift', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class ShiftController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Lists all shift entities in an event.
     */
    #[Route(path: '/{event}/index', name: 'shift_index', methods: ['GET'])]
    public function indexAction(Request $request, $access, Event $event)
    {
        // Again, ajax/html-centric. But maybe return json later.
        if ($this->isRest($access)) {
            return $this->render('shift/_index.html.twig', array(
                'event' => $event,
            ));
        }
        return $this->render('shift/index.html.twig', array(
            'event' => $event,
        ));
    }

    /**
     * Finds and displays a event entity.
     */
    #[Route(path: '/{id}/show_printable', name: 'shift_show_printable', methods: ['GET'])]
    public function showPrintableAction(Request $request, Shift $shift)
    {
        return $this->render('shift/printable.html.twig', array(
            'shift' => $shift,
            'booked' => true,
            'all' => true,
        ));
    }

    /**
     * Creates a new shift entity.
     */
    /*
     * This one is quite hacked together, but it's because I have to keep the
     * experimentation visible for later attempts at doing what I want to do.
     * Which is to return the form as HTML if it's ajax and web, and if it's
     * REST (apps you know..) Return JSON.
     *
     */
    #[Route(path: '/new', name: 'shift_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, $access)
    {
        $shift = new Shift();

        // If this has a event set here, it's not an invalid create attempt.
        $event = null;
        if ($event_id = $request->get('event')) {
            $event = $this->entityManager->getRepository(Event::class)->find($event_id);
        }
        if ($from_shift = $request->get('from_shift')) {
            $from_shift = $this->entityManager->getRepository(Shift::class)->find($from_shift);
            $event = $from_shift->getEvent();
        }

        // Want an Event, always.
        if (!$event)
            throw $this->createNotFoundException();
        $shift->setEvent($event);

        $form = $this->createForm('App\Form\ShiftType', $shift);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $this->entityManager->persist($shift);
                $this->entityManager->flush($shift);
                if ($this->isRest($access)) {
                    return new JsonResponse(array("status" => "OK"),
                        Response::HTTP_CREATED);
                } else { 
                    return $this->redirectToRoute('event_show',
                        array('id' => $shift->getEvent()->getId()));
                }
            } else {
                // The issue here is that I want to return this if it's a
                // /rest/ call, but not /ajax/. If AJAX, return a prettier
                // text.
                if ($this->isRest($access)) {
                    return $this->returnErrorResponse("Validation Error",
                        400, $this->handleFormErrors($form));
                }
            }
        }

        // We either start with a shift to copy from, or event. Preferrably
        // shift.
        if ($from_shift) {
            $shift->setStart($from_shift->getStart());
            $shift->setEnd($from_shift->getEnd());
            $form->setData($shift);
        } else {
            $shift->setStart(clone($event->getStart()));
            $shift->setEnd($event->getEnd());
            $form->setData($shift);
        }

        /*
         * Not sure yet how to handle pure REST, keep ajax for now.
         * (And I can start being annoyed by "isRest" which means both ajax
         * and rest.  (But I can test on accept-header and return the
         * _new-template if HTML is asked for. returnRest with a set template
         * does fix that part)
         */
        if ($this->isRest($access)) {
            return $this->render('shift/_new.html.twig', array(
                'shift' => $shift,
                'form' => $form->createView(),
            ));
        }

        return $this->render('shift/new.html.twig', array(
            'shift' => $shift,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing shift entity.
     */
    #[Route(path: '/{id}/edit', name: 'shift_edit', defaults: ['id' => 0], methods: ['GET', 'POST'])]
    public function editAction(Request $request, Shift $shift, $access)
    {
        $editForm = $this->createForm('App\Form\ShiftType', $shift);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('event_show', array('id' => $shift->getEvent()->getId()));
        }

        if ($this->isRest($access)) {
            return $this->render('shift/_edit.html.twig', array(
                'shift' => $shift,
                'edit_form' => $editForm->createView()
            ));
        }

        return $this->render('shift/edit.html.twig', array(
            'shift' => $shift,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a shift entity.
     */
    #[Route(path: '/{id}', name: 'shift_delete', methods: ['DELETE'])]
    public function deleteAction(Request $request, $access, Shift $shift)
    {
        $event = $shift->getEvent();
        // Bloody good question here, because CSRF.
        // This should add some sort of protection.
        if ($this->isRest($access)) {
            $this->entityManager->remove($shift);
            $this->entityManager->flush($shift);
            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }

        $form = $this->createDeleteForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($shift);
            $this->entityManager->flush($shift);
        }
        return $this->redirectToRoute('event_show',
            array('id' => $event->getId()));
    }

    /**
     * Sets a (new) state on the shift.
     */
    #[Route(path: '/{id}/state/{state}', name: 'shift_state', methods: ['POST'])]
    public function stateAction(Request $request, Shift $shift, $state, $access)
    {
        if ($state != $shift->getState()) {
            $shift->setState($state);
            $this->entityManager->flush($shift);
        }
        if ($this->isRest($access)) {
            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }

        $event = $shift->getEvent()->getParent() ?? $shift->getEvent();
        $event_id = $request->request->get('event') ?? $event->getId();
        return $this->redirectToRoute('event_show', array(
            'id' => $event_id));
    }

    /**
     * Very simple, but useful.
     */
    #[Route(path: '/{id}/amounts', name: 'shift_amounts', defaults: ['id' => 0], methods: ['GET'])]
    public function amountsAction(Shift $shift)
    {
        $shiftamounts            = $shift->getJobsAmountByState();
        $shiftamounts['amount']  = $shift->getAmount();
        $shiftamounts['booked']  = $shift->getBookedAmount();
        $shiftamounts['needing'] = $shift->getBookedAmount() - $shift->getBookedAmount();

        return new JsonResponse([
            "status" => "OK",
            "shiftamounts" => $shiftamounts
            ], Response::HTTP_CREATED);
    }

    /**
     * Sends messages to a batch of persons.
     */
    #[Route(path: '/{id}/send_message', name: 'shift_send_message', methods: ['POST'])]
    public function sendMessageAction($access, Request $request, Shift $shift, SakonninMessages $sakonninMessages)
    {
        $body = $request->request->get('body');
        $subject = $request->request->get('subject') ?? "Message from CrewCall";

        $filter = [];
        if ($states = $request->request->get('states')) {
            if (!in_array("all", $states))
                $filter['states'] = $states;
        }

        if ($state = $request->request->get('state'))
            $filter['states'] = [$state];

        $people = new ArrayCollection();
        foreach ($shift->getJobs($filter) as $j) {
            if (!$people->contains($j->getPerson()))
                $people->add($j->getPerson());
        }
        $person_contexts = array_map(function($person) {
            return [
                'system' => 'crewcall',
                'object_name' => 'person',
                'external_id' => $person->getId()
            ];
            }, $people->toArray());
        $message_type = $request->request->get('message_type');
        $sakonninMessages->postMessage(array(
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

    /**
     * Finds and displays the gedmo loggable history
     */
    #[Route(path: '/{id}/log', name: 'shift_log')]
    public function showLogAction(Request $request, $access, $id)
    {
        return  $this->showLogPage($request,$access, Shift::class, $id);
    }

    /**
     * Creates a form to delete a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Shift $shift)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_delete', array('id' => $shift->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /*
     * Notes stuff.
     * I'd put it in a trait if it werent for it all being easier this way.
     * (But I will more or less cut&paste from here to the other places needing
     * this. Just replace shift with i.e. shift.)
     */
    
    #[Route(path: '/{shift}/add_note', name: 'shift_add_note', methods: ['POST'])]
    public function addNoteAction(Request $request, Shift $shift, $access)
    {
        $token = $request->request->get('_csrf_token');

        if ($token && $this->isCsrfTokenValid('shift-add-note', $token)) {
            // Let's hope csrf token checks is enough.
            $shift->addNote([
                'id' => $request->request->get('note_id'),
                'type' => $request->request->get('type'),
                'subject' => $request->request->get('subject'),
                'body' => $request->request->get('body')
            ]);
            $this->entityManager->flush();
        }
        $event = $shift->getEvent()->getParent() ?? $shift->getEvent();
        $event_id = $request->request->get('event') ?? $event->getId();
        return $this->redirectToRoute('event_show', array(
            'id' => $event_id));
    }

    
    #[Route(path: '/{shift}/{note_id}/edit_note', name: 'shift_edit_note', methods: ['POST'])]
    public function editNoteAction(Request $request, Shift $shift, $note_id, $access)
    {
        $token = $request->request->get('_csrf_token');

        if ($token && $this->isCsrfTokenValid('shift-edit-note'.$note_id, $token)) {
            $shift->updateNote([
                'id' => $note_id,
                'type' => $request->request->get('type'),
                'subject' => $request->request->get('subject'),
                'body' => $request->request->get('body')
            ]);
            $this->entityManager->flush();
        }
        $event = $shift->getEvent()->getParent() ?? $shift->getEvent();
        $event_id = $request->request->get('event') ?? $event->getId();
        return $this->redirectToRoute('event_show', array(
            'id' => $event_id));
    }

    
    #[Route(path: '/{shift}/{note_id}/remove_note', name: 'shift_remove_note', methods: ['POST'])]
    public function removeNoteAction(Request $request, Shift $shift, $note_id, $access)
    {
        $token = $request->request->get('_csrf_token');

        if ($token && $this->isCsrfTokenValid('shift-remove-note'.$note_id, $token)) {
            $shift->removeNote($note_id);
            $this->entityManager->flush();
        }
        $event = $shift->getEvent()->getParent() ?? $shift->getEvent();
        $event_id = $request->request->get('event') ?? $event->getId();
        return $this->redirectToRoute('event_show', array(
            'id' => $event_id));
    }
}
