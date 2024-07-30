<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Intl\Countries;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Event;
use App\Entity\Person;
use App\Entity\Organization;
use App\Entity\Location;
use App\Service\Dashboarder;

#[IsGranted("ROLE_USER")]
class DefaultController extends AbstractController
{
    use \BisonLab\CommonBundle\Controller\CommonControllerTrait;
    use \BisonLab\ContextBundle\Controller\ContextTrait;

    #[Route(path: '/', name: 'index')]
    public function indexAction(Request $request, EntityManagerInterface $entityManager)
    {
        // Pracitally only used by auth.
        if ($user = $this->getUser()) {
            $user->setLastLogin(new \DateTime());
            $entityManager->flush();
            if ($user->isAdmin())
                return $this->redirectToRoute('dashboard');
            else
                return $this->redirectToRoute('user_view');
        }
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/dashboard', name: 'dashboard')]
    public function dashboardAction(Request $request, Dashboarder $dashboarder)
    {
        if (!$this->getUser()->isAdmin())
            return $this->redirectToRoute('user_view');

        return $this->render('default/index.html.twig',
            ['dashboarder' => $dashboarder]);
    }

    #[Route(path: '/admin/global_search', name: 'global_search')]
    public function globalSearchAction(Request $request, EntityManagerInterface $entityManager)
    {
        $value = $request->get('value');
        if (empty($value))
            return $this->redirect($this->generateUrl('dashboard'));

        // For now, always web.
        $access = "web";
        $events = new \Doctrine\Common\Collections\ArrayCollection();
        $persons = new \Doctrine\Common\Collections\ArrayCollection();
        $locations = new \Doctrine\Common\Collections\ArrayCollection();
        $organizations = new \Doctrine\Common\Collections\ArrayCollection();

        foreach (array('name', 'description') as $field) {
            $result = $entityManager->getRepository(Event::class)
                        ->searchByField($field, trim($value));
            foreach ($result as $i) {
                if (!$events->contains($i))
                    $events->add($i);
            }
        }

        foreach (array('email', 'username', 'full_name', 'mobile_phone_number', 'nationality') as $field) {

            // This looks odd but it may occationally even work with the users
            // own locale.
            if ($field == 'nationality') {
                $a3s = Countries::getAlpha3Names();
                $countries = array_change_key_case(array_flip($a3s));
                if ($nval = $countries[strtolower($value)] ?? false)
                    $result = $entityManager->getRepository(Person::class)
                            ->searchByField($field, trim($nval));
            } else {
                $result = $entityManager->getRepository(Person::class)
                        ->searchByField($field, trim($value));
            }
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
            $result = $entityManager->getRepository(Location::class)
                        ->searchByField($field, trim($value));
            foreach ($result as $i) {
                if (!$locations->contains($i))
                    $locations->add($i);
            }
        }

        foreach (array('name', 'organization_number') as $field) {
            $result = $entityManager->getRepository(Organization::class)
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

    #[Route(path: '/ping', name: 'ping')]
    public function pingAction(Request $request)
    {
        $response = $request->request->all();
        $response = array_merge($response, $request->query->all());
        $response['response'] = "ACK";
        $response['code'] = "42";
        return $this->returnRestData($request, $response);
    }
}
