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

use App\Entity\Organization;
use App\Entity\ShiftOrganization;

/**
 * Shiftorganization controller.
 */
#[Route(path: '/admin/{access}/shiftorganization', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class ShiftOrganizationController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Lists all shiftOrganization entities.
     */
    #[Route(path: '/', name: 'shiftorganization_index', methods: ['GET'])]
    public function indexAction(Request $request, $access)
    {
        if ($shift_id = $request->get('shift')) {
            if ($shift = $this->entityManager->getRepository(Shift::class)->find($shift_id)) {
                $shiftOrganizations = $shift;
            }
        } else {
            $shiftOrganizations = $this->entityManager->getRepository(ShiftOrganization::class)->findAll();
        }

        // Again, ajax-centric.
        if ($this->isRest($access)) {
            return $this->render('shiftorganization/_index.html.twig', array(
                'shiftOrganizations' => $shiftOrganizations,
            ));
        }
        return $this->render('shiftorganization/index.html.twig', array(
            'shiftOrganizations' => $shiftOrganizations,
        ));
    }

    /**
     * Creates a new shiftOrganization entity.
     */
    #[Route(path: '/new', name: 'shiftorganization_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, $access)
    {
        $shiftOrganization = new Shiftorganization();
        $form = $this->createForm('App\Form\ShiftOrganizationType', $shiftOrganization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($shiftOrganization);
            $this->entityManager->flush($shiftOrganization);

            if ($this->isRest($access)) {
                return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
            } else { 
                return $this->redirectToRoute('shiftorganization_show', array('id' => $shiftOrganization->getId()));
            }
        }

        // If this has a shift set here, it's not an invalid create attempt.
        if ($shift_id = $request->get('shift')) {
            if ($shift = $this->entityManager->getRepository(Shift::class)->find($shift_id)) {
                $shiftOrganization->setShift($shift);
                $form->setData($shiftOrganization);
            }
        }
        if ($this->isRest($access)) {
            return $this->render('shiftorganization/_new.html.twig', array(
                'shiftOrganization' => $shiftOrganization,
                'form' => $form->createView(),
            ));
        }
        return $this->render('shiftorganization/new.html.twig', array(
            'shiftOrganization' => $shiftOrganization,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a shiftOrganization entity.
     */
    #[Route(path: '/{id}', name: 'shiftorganization_show', methods: ['GET'])]
    public function showAction(ShiftOrganization $shiftOrganization)
    {
        $deleteForm = $this->createDeleteForm($shiftOrganization);

        return $this->render('shiftorganization/show.html.twig', array(
            'shiftOrganization' => $shiftOrganization,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing shiftOrganization entity.
     */
    #[Route(path: '/{id}/edit', name: 'shiftorganization_edit', defaults: ['id' => 0], methods: ['GET', 'POST'])]
    public function editAction(Request $request, ShiftOrganization $shiftOrganization, $access)
    {
        $deleteForm = $this->createDeleteForm($shiftOrganization);
        $editForm = $this->createForm('App\Form\ShiftOrganizationType', $shiftOrganization);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->entityManager->flush();

            if ($this->isRest($access)) {
                // No content, well, sortof.
                return new JsonResponse(array("status" => "OK"), Response::HTTP_OK);
            } else {
                return $this->redirectToRoute('shiftorganization_show', array('id' => $shiftOrganization->getId()));
            }
        }

        if ($this->isRest($access)) {
            return $this->render('shiftorganization/_edit.html.twig', array(
            'shiftOrganization' => $shiftOrganization,
                'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
            ));
        }
        return $this->render('shiftorganization/edit.html.twig', array(
            'shiftOrganization' => $shiftOrganization,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a shiftOrganization entity.
     */
    #[Route(path: '/{id}', name: 'shiftorganization_delete', methods: ['DELETE'])]
    public function deleteAction(Request $request, ShiftOrganization $shiftOrganization, $access)
    {
        // If rest, no form.
        if ($this->isRest($access)) {
            $this->entityManager->remove($shiftOrganization);
            $this->entityManager->flush($shiftOrganization);
            return new JsonResponse(array("status" => "OK"), Response::HTTP_NO_CONTENT);
        }

        $form = $this->createDeleteForm($shiftOrganization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($shiftOrganization);
            $this->entityManager->flush($shiftOrganization);
        }

        return $this->redirectToRoute('shiftorganization_index');
    }

    /**
     * Creates a form to delete a shiftOrganization entity.
     *
     * @param ShiftOrganization $shiftOrganization The shiftOrganization entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(ShiftOrganization $shiftOrganization)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('shiftorganization_delete', array('id' => $shiftOrganization->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
