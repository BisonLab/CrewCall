<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

use App\Entity\PersonFunction;
use App\Entity\Person;
use App\Entity\FunctionEntity;

/**
 * PersonFunction controller.
 *
 * @Route("/admin/{access}/personfunction", defaults={"access" = "web"}, requirements={"access": "web|rest|ajax"})
 */
class PersonFunctionController extends CommonController
{
    /**
     * Creates a new personFunction entity.
     * Pure REST/AJAX.
     *
     * @Route("/new", name="personfunction_new", methods={"POST"})
     */
    public function newAction(Request $request, $access)
    {
        $fe_id = $request->request->get('function_id');
        $p_id  = $request->request->get('person_id');

        if (!$fe_id || !$p_id)
            return new JsonResponse(array("error" => "Missing person or function ID"), Response::HTTP_UNPROCESSABLE_ENTITY);


        $em = $this->getDoctrine()->getManager();
        $function = $em->getRepository(FunctionEntity::class)->find($fe_id);
        $person = $em->getRepository(Person::class)->find($p_id);
        if (!$person || !$function) {
            return new JsonResponse(array("error" => "Could not find person or function"), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
         
        $pf = new PersonFunction();
        $pf->setFunction($function);
        $person->addPersonFunction($pf);
        if ($from_date = $request->request->get('from_date')) {
            $pf->setFromDate(new \DateTime($from_date));
        } else {
            $pf->setFromDate(new \DateTime());
        }
        $em->persist($pf);
        $em->flush($pf);

        return new JsonResponse(array("status" => "OK"), Response::HTTP_CREATED);
    }

    /**
     * Finds and displays a personFunction entity.
     *
     * @Route("/{id}", name="personfunction_show", methods={"GET"})
     */
    public function showAction(PersonFunction $personFunction)
    {
        $deleteForm = $this->createDeleteForm($personFunction);

        return $this->render('personfunction/show.html.twig', array(
            'personFunction' => $personFunction,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing personFunction entity.
     *
     * @Route("/{id}/edit", name="personfunction_edit", methods={"GET", "POST"})
     */
    public function editAction(Request $request, PersonFunction $personFunction)
    {
        $deleteForm = $this->createDeleteForm($personFunction);
        $editForm = $this->createForm('App\Form\PersonFunctionType', $personFunction);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('personfunction_show', array('id' => $personFunction->getId()));
        }

        return $this->render('personfunction/edit.html.twig', array(
            'personFunction' => $personFunction,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a personFunction entity.
     *
     * @Route("/{id}", name="personfunction_delete", methods={"DELETE"})
     */
    public function deleteAction(Request $request, PersonFunction $personFunction)
    {
        $form = $this->createDeleteForm($personFunction);
        $form->handleRequest($request);
        $person = $personFunction->getPerson();
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($personFunction);
            $em->flush($personFunction);
        }
        return $this->redirectToRoute('person_show', array('id' => $person->getId()));
    }

    /**
     * Creates a form to delete a personFunction entity.
     *
     * @param PersonFunction $personFunction The personFunction entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    public function createDeleteForm(PersonFunction $personFunction)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('personfunction_delete', array('id' => $personFunction->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
