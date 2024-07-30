<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use App\Entity\Organization;
use App\Entity\PersonRoleOrganization;
use App\Entity\Person;
use App\Entity\Role;
use App\Service\Addressing;

/**
 * Organization controller.
 */
#[Route(path: '/admin/{access}/organization', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class OrganizationController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
        private ParameterBagInterface $parameterBag,
        private Addressing $addressing,
    ) {
    }

    /**
     * Lists all organization entities.
     */
    #[Route(path: '/', name: 'organization_index', methods: ['GET'])]
    public function indexAction()
    {
        $organizations = $this->entityManager->getRepository(Organization::class)->findAll();

        return $this->render('organization/index.html.twig', array(
            'organizations' => $organizations,
        ));
    }

    /**
     * Creates a new organization entity.
     */
    #[Route(path: '/new', name: 'organization_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $organization = new Organization();
        $addressing_config = $this->parameterBag->get('addressing');
        $address_elements = $this->addressing->getFormElementList($organization->getVisitAddress());
        $form = $this->createForm('App\Form\OrganizationType',
            $organization, [
                'addressing_config' => $addressing_config,
                'address_elements' => $address_elements
            ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($organization);
            $this->entityManager->flush($organization);

            return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
        }

        return $this->render('organization/new.html.twig', array(
            'organization' => $organization,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a organization entity.
     */
    #[Route(path: '/{id}', name: 'organization_show', methods: ['GET'])]
    public function showAction(Organization $organization)
    {
        $deleteForm = $this->createDeleteForm($organization);

        return $this->render('organization/show.html.twig', array(
            'delete_form' => $deleteForm->createView(),
            'organization' => $organization,
        ));
    }

    /**
     * Displays a form to edit an existing organization entity.
     */
    #[Route(path: '/{id}/edit', name: 'organization_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Organization $organization)
    {
        $addressing_config = $this->parameterBag->getParameter('addressing');
        $address_elements = $this->addressing->getFormElementList($organization->getVisitAddress());

        $editForm = $this->createForm('App\Form\OrganizationType',
            $organization, [
                'addressing_config' => $addressing_config,
                'address_elements' => $address_elements
            ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
        }

        return $this->render('organization/edit.html.twig', array(
            'organization' => $organization,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a organization entity.
     */
    #[Route(path: '/{id}', name: 'organization_delete', methods: ['DELETE'])]
    public function deleteAction(Request $request, Organization $organization)
    {
        $form = $this->createDeleteForm($organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($organization);
            $this->entityManager->flush($organization);
        }

        return $this->redirectToRoute('organization_index');
    }

    /**
     * Finds and displays the gedmo loggable history
     */
    #[Route(path: '/{id}/log', name: 'organization_log')]
    public function showLogAction(Request $request, $access, $id)
    {
        return  $this->showLogPage($request,$access, Organization::class, $id);
    }

    /**
     * Creates a form to delete a organization entity.
     *
     * @param Organization $organization The organization entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Organization $organization)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('organization_delete', array('id' => $organization->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * Creates a new PersonRoleOrganization entity.
     */
    #[Route(path: '/{id}/add_person', name: 'organization_add_person', methods: ['GET', 'POST'])]
    public function addPersonAction(Request $request, Organization $organization, $access)
    {
        $pro = new PersonRoleOrganization();
        // Default-hack
        $pro->setOrganization($organization);

        $exists_form = $this->createForm('App\Form\ExistingPersonOrganizationType', $pro);
        $exists_form->handleRequest($request);

        $new_form = $this->createForm('App\Form\NewPersonOrganizationType');
        $new_form->handleRequest($request);

        if ($exists_form->isSubmitted() && $exists_form->isValid()) {
            $this->entityManager->persist($pro);
            $this->entityManager->flush($pro);

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else {
                return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
            }
        }

        if ($new_form->isSubmitted() && $new_form->isValid()) {
            $person = new Person();
            $person->setState("EXTERNAL");
            $new_form_data = $new_form->getData();
            $person->setMobilePhoneNumber($new_form_data['mobile_phone_number']);
            // Yeah, always contact. Need a default. Using just mobile phone is
            // tempting aswell.
            $username = "CONTACT" . \ShortCode\Random::get(6);
            $person->setUsername($username);
            // And we do need an email address, which can be random aswell.
            // (Yeah, I do not like it. But this is users not going to log in,
            // so it's not really that bad.)
            if (empty($new_form_data['email']))
                $person->setEmail($username . "@crewcall.local");
            else
                $person->setEmail($new_form_data['email']);
            $person->setFirstName($new_form_data['first_name']);
            $person->setLastName($new_form_data['last_name']);
            $person->setPassword(sprintf("%16x", rand()));

            $this->entityManager->persist($person);
            $pro->setPerson($person);
            $pro->setRole($new_form_data['role']);
            $pro->setOrganization($new_form_data['organization']);

            $this->entityManager->persist($person);
            $this->entityManager->persist($pro);
            $this->entityManager->flush();

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else {
                return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
            }
        }

        if ($contact = $this->entityManager->getRepository(Role::class)->findOneBy(['name' => 'Contact'])) {
            $exists_form->get('role')->setData($contact);
            $new_form->get('role')->setData($contact);
        }
        $new_form->get('organization')->setData($organization);

        if ($this->isRest($access)) {
            return $this->render('organization/_new_pfo.html.twig', array(
                'pro' => $pro,
                'organization' => $organization,
                'exists_form' => $exists_form->createView(),
                'new_form' => $new_form->createView(),
            ));
        }
    }

    /**
     * Removes a PersonRoleOrganization entity.
     * Pure REST/AJAX.
     */
    #[Route(path: '/{id}/remove_person', name: 'organization_remove_person', methods: ['GET', 'DELETE', 'POST'])]
    public function removePersonAction(Request $request, PersonRoleOrganization $pro, $access)
    {
        $organization = $pro->getOrganization();
        $this->entityManager->remove($pro);
        $this->entityManager->flush($pro);
        if ($this->isRest($access)) {
            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }
        return $this->redirectToRoute('organization_show',
            array('id' => $organization->getId()));
    }
}
