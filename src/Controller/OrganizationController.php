<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\Organization;
use App\Entity\PersonRoleOrganization;
use App\Entity\Person;
use App\Entity\Role;

/**
 * Organization controller.
 *
 * @Route("/admin/{access}/organization", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class OrganizationController extends CommonController
{
    /**
     * Lists all organization entities.
     *
     * @Route("/", name="organization_index", methods={"GET"})
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $organizations = $em->getRepository(Organization::class)->findAll();

        return $this->render('organization/index.html.twig', array(
            'organizations' => $organizations,
        ));
    }

    /**
     * Creates a new organization entity.
     *
     * @Route("/new", name="organization_new", methods={"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $organization = new Organization();
        $addressing_config = $this->container->getParameter('addressing');
        $addressing = $this->container->get('crewcall.addressing');
        $address_elements = $addressing->getFormElementList($organization->getVisitAddress());
        $form = $this->createForm('App\Form\OrganizationType',
            $organization, [
                'addressing_config' => $addressing_config,
                'address_elements' => $address_elements
            ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($organization);
            $em->flush($organization);

            return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
        }

        return $this->render('organization/new.html.twig', array(
            'organization' => $organization,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a organization entity.
     *
     * @Route("/{id}", name="organization_show", methods={"GET"})
     */
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
     *
     * @Route("/{id}/edit", name="organization_edit", methods={"GET", "POST"})
     */
    public function editAction(Request $request, Organization $organization)
    {
        $addressing_config = $this->container->getParameter('addressing');
        $addressing = $this->container->get('crewcall.addressing');
        $address_elements = $addressing->getFormElementList($organization->getVisitAddress());

        $editForm = $this->createForm('App\Form\OrganizationType',
            $organization, [
                'addressing_config' => $addressing_config,
                'address_elements' => $address_elements
            ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
        }

        return $this->render('organization/edit.html.twig', array(
            'organization' => $organization,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a organization entity.
     *
     * @Route("/{id}", name="organization_delete", methods={"DELETE"})
     */
    public function deleteAction(Request $request, Organization $organization)
    {
        $form = $this->createDeleteForm($organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($organization);
            $em->flush($organization);
        }

        return $this->redirectToRoute('organization_index');
    }

    /**
     * Finds and displays the gedmo loggable history
     *
     * @Route("/{id}/log", name="organization_log")
     */
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
     *
     * @Route("/{id}/add_person", name="organization_add_person", methods={"GET", "POST"})
     */
    public function addPersonAction(Request $request, Organization $organization, $access)
    {
        $em = $this->getDoctrine()->getManager();
        $pro = new PersonRoleOrganization();
        // Default-hack
        $pro->setOrganization($organization);

        $exists_form = $this->createForm('App\Form\ExistingPersonOrganizationType', $pro);
        $exists_form->handleRequest($request);

        $new_form = $this->createForm('App\Form\NewPersonOrganizationType');
        $new_form->handleRequest($request);

        if ($exists_form->isSubmitted() && $exists_form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($pro);
            $em->flush($pro);

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else {
                return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
            }
        }

        if ($new_form->isSubmitted() && $new_form->isValid()) {
            $em = $this->getDoctrine()->getManager();
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

            $em->persist($person);
            $pro->setPerson($person);
            $pro->setRole($new_form_data['role']);
            $pro->setOrganization($new_form_data['organization']);

            $em->persist($person);
            $em->persist($pro);
            $em->flush();

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else {
                return $this->redirectToRoute('organization_show', array('id' => $organization->getId()));
            }
        }

        if ($contact = $em->getRepository(Role::class)->findOneBy(['name' => 'Contact'])) {
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
     *
     * @Route("/{id}/remove_person", name="organization_remove_person", methods={"GET", "DELETE", "POST"})
     */
    public function removePersonAction(Request $request, PersonRoleOrganization $pro, $access)
    {
        $organization = $pro->getOrganization();
        $em = $this->getDoctrine()->getManager();
        $em->remove($pro);
        $em->flush($pro);
        if ($this->isRest($access)) {
            return new JsonResponse(array("status" => "OK"),
                Response::HTTP_OK);
        }
        return $this->redirectToRoute('organization_show',
            array('id' => $organization->getId()));
    }
}
