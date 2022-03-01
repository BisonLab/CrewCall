<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use BisonLab\CommonBundle\Controller\CommonController as CommonController;

class DefaultController extends CommonController
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request)
    {
        if ($user = $this->getUser()) {
            if ($user->isAdmin())
                return $this->redirectToRoute('dashboard');
            else
                return $this->redirectToRoute('user_view');
        }
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboardAction(Request $request)
    {
        if (!$this->getUser()->isAdmin())
            return $this->redirectToRoute('user_view');

        $dashboarder = $this->get('crewcall.dashboarder');
        return $this->render('default/index.html.twig',
            ['dashboarder' => $dashboarder]);
    }

    /**
     * @Route("/admin/global_search", name="global_search")
     */
    public function globalSearchAction(Request $request)
    {
        $value = $request->get('value');
        if (empty($value))
            return $this->redirect($this->generateUrl('dashboard'));

        $em = $this->getDoctrine()->getManager();

        // For now, always web.
        $access = "web";
        $events = new \Doctrine\Common\Collections\ArrayCollection();
        $persons = new \Doctrine\Common\Collections\ArrayCollection();
        $locations = new \Doctrine\Common\Collections\ArrayCollection();
        $organizations = new \Doctrine\Common\Collections\ArrayCollection();

        foreach (array('name', 'description') as $field) {
            $result = $em->getRepository('App:Event')
                        ->searchByField($field, trim($value));
            foreach ($result as $i) {
                if (!$events->contains($i))
                    $events->add($i);
            }
        }

        foreach (array('email', 'username', 'full_name', 'mobile_phone_number') as $field) {
            $result = $em->getRepository('App:Person')
                        ->searchByField($field, trim($value));
            foreach ($result as $i) {
                if (!$persons->contains($i))
                    $persons->add($i);
            }
        }

        // A direct hit.
        if ($persons->count() == 1 && (string)$persons->first() == $value) {
            return $this->redirect($this->generateUrl('person_show',
                array('id' => $persons->first()->getId(), 'access' => $access)));
        }

        foreach (array('name', 'description') as $field) {
            $result = $em->getRepository('App:Location')
                        ->searchByField($field, trim($value));
            foreach ($result as $i) {
                if (!$locations->contains($i))
                    $locations->add($i);
            }
        }

        foreach (array('name', 'organization_number') as $field) {
            $result = $em->getRepository('App:Organization')
                        ->searchByField($field, trim($value));
            foreach ($result as $i) {
                if (!$organizations->contains($i))
                    $organizations->add($i);
            }
        }

        $zilch = $value;
        if ($events->count() > 0
                || $persons->count() > 0
                || $locations->count() > 0
                || $organizations->count() > 0)
            $zilch = false;

        return $this->render('default/globalSearchResult.html.twig',
            array(
                'value'  => $value,
                'zilch'  => $zilch,
                'events'  => $events,
                'persons'  => $persons,
                'locations'  => $locations,
                'organizations' => $organizations,
            ));
    }

    /**
     * @Route("/ping", name="ping")
     */
    public function pingAction(Request $request)
    {
        $response = $request->request->all();
        $response = array_merge($response, $request->query->all());
        $response['response'] = "ACK";
        $response['code'] = "42";
        return $this->returnRestData($request, $response);
    }
}
