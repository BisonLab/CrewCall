<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Location;
use App\Entity\PersonRoleLocation;
use App\Entity\Person;
use App\Entity\FunctionEntity;
use App\Service\Addressing;

/**
 * Location controller.
 */
#[Route(path: '/admin/{access}/location', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class LocationController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Addressing $addressing,
    ) {
    }

    /**
     * Lists all location entities.
     */
    #[Route(path: '/', name: 'location_index', methods: ['GET'])]
    public function indexAction()
    {
        $locations = $this->entityManager->getRepository(Location::class)->findAll();

        return $this->render('location/index.html.twig', array(
            'locations' => $locations,
        ));
    }

    /**
     * Creates a new location entity.
     */
    #[Route(path: '/new', name: 'location_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $location = new Location();
        if ($parent_id = $request->get('parent')) {
            if ($parent = $this->entityManager->getRepository(Location::class)->find($parent_id)) {
                $location->setParent($parent);
            }
        }
        $address_elements = $this->addressing->getFormElementList($location);
        $form = $this->createForm('App\Form\LocationType', $location, ['address_elements' => $address_elements]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($location);
            $this->entityManager->flush($location);

            return $this->redirectToRoute('location_show', array('id' => $location->getId()));
        }

        return $this->render('location/new.html.twig', array(
            'location' => $location,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a location entity.
     */
    #[Route(path: '/{id}', name: 'location_show', methods: ['GET'])]
    public function showAction(Location $location)
    {
        $deleteForm = $this->createDeleteForm($location);
        return $this->render('location/show.html.twig', array(
            'location' => $location,
            'address_string' => $this->addressing->compose($location->getAddress(), 'string'),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing location entity.
     */
    #[Route(path: '/{id}/edit', name: 'location_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Location $location)
    {
        $address_elements = $this->addressing->getFormElementList($location);
        $editForm = $this->createForm('App\Form\LocationType', $location, ['address_elements' => $address_elements]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            return $this->redirectToRoute('location_show', array('id' => $location->getId()));
        }

        return $this->render('location/edit.html.twig', array(
            'location' => $location,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Creates a new PersonRoleLocation entity.
     */
    #[Route(path: '/{id}/add_person', name: 'location_add_person', methods: ['GET', 'POST'])]
    public function addPersonAction(Request $request, Location $location, $access)
    {
        $pfl = new PersonRoleLocation();
        // Default-hack
        $pfl->setLocation($location);

        $exists_form = $this->createForm('App\Form\ExistingPersonLocationType', $pfl);
        $exists_form->handleRequest($request);

        $new_form = $this->createForm('App\Form\NewPersonLocationType');
        $new_form->handleRequest($request);

        if ($exists_form->isSubmitted() && $exists_form->isValid()) {
            $this->entityManager->persist($pfl);
            $this->entityManager->flush($pfl);

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else {
                return $this->redirectToRoute('location_show', array('id' => $location->getId()));
            }
        }

        if ($new_form->isSubmitted() && $new_form->isValid()) {
            $person = new Person();
            $person->setState("EXTERNAL");
            $new_form_data = $new_form->getData();
            $person->setMobilePhoneNumber($new_form_data['mobile_phone_number']);
            // Yeah, always contact. Need a default. Using just mobile phone is
            // tempting aswell.
            // And we do need an email address, which can be random aswell.
            // (Yeah, I do not like it. But this is users not going to log in,
            // so it's not really that bad.)
            $username = "CONTACT" . \ShortCode\Random::get(6);
            $person->setUsername($username);
            if (empty($new_form_data['email']))
                $person->setEmail($username . "@crewcall.local");
            else
                $person->setEmail($new_form_data['email']);

            $person->setFirstName($new_form_data['first_name']);
            $person->setLastName($new_form_data['last_name']);
            $person->setPassword(sprintf("%16x", rand()));

            $this->entityManager->persist($person);
            $pfl->setPerson($person);
            $pfl->setFunction($new_form_data['function']);
            $pfl->setLocation($new_form_data['location']);

            $this->entityManager->persist($person);
            $this->entityManager->persist($pfl);
            $this->entityManager->flush();

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else {
                return $this->redirectToRoute('location_show', array('id' => $location->getId()));
            }
        }

        if ($contact = $this->entityManager->getRepository(FunctionEntity::class)->findOneBy(['name' => 'Contact'])) {
            $exists_form->get('function')->setData($contact);
            $new_form->get('function')->setData($contact);
        }
        $new_form->get('location')->setData($location);

        if ($this->isRest($access)) {
            return $this->render('location/_new_pfl.html.twig', array(
                'pfl' => $pfl,
                'location' => $location,
                'exists_form' => $exists_form->createView(),
                'new_form' => $new_form->createView(),
            ));
        }
    }

    /**
     * Removes a personRoleLocation entity.
     * Pure REST/AJAX.
     */
    #[Route(path: '/{id}/remove_person', name: 'location_remove_person', methods: ['GET', 'DELETE', 'POST'])]
    public function removePersonAction(Request $request, PersonRoleLocation $prl, $access)
    {
        $location = $prl->getLocation();
        $this->entityManager->remove($prl);
        $this->entityManager->flush($prl);
        if ($this->isRest($access)) {
            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }
        return $this->redirectToRoute('location_show',
            array('id' => $location->getId()));
    }

    /**
     * Deletes a location entity.
     */
    #[Route(path: '/{id}', name: 'location_delete', methods: ['DELETE'])]
    public function deleteAction(Request $request, Location $location)
    {
        $form = $this->createDeleteForm($location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($location);
            $this->entityManager->flush($location);
        }

        return $this->redirectToRoute('location_index');
    }

    /**
     * Finds and displays the gedmo loggable history
     */
    #[Route(path: '/{id}/log', name: 'location_log')]
    public function showLogAction(Request $request, $access, $id)
    {
        return  $this->showLogPage($request,$access, Location::class, $id);
    }

    /**
     * Creates a form to delete a location entity.
     *
     * @param Location $location The location entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Location $location)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('location_delete', array('id' => $location->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
