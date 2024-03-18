<?php

namespace App\Controller;

use App\Entity\Person;
use App\Entity\PersonFunction;
use App\Entity\PersonRoleOrganization;
use App\Entity\FunctionEntity;
use App\Entity\Role;
use App\Entity\Organization;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use BisonLab\CommonBundle\Controller\CommonController as CommonController;

class RegistrationController extends CommonController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier, ParameterBagInterface $params)
    {
        $this->emailVerifier = $emailVerifier;
        $this->params = $params;
    }

    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new Person();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $feRepo = $entityManager->getRepository(FunctionEntity::class);
        $pickable_functions = $feRepo->findPickableFunctions();
        if (count($pickable_functions) > 0) {
            $form->add('function', EntityType::class, [
                    'label' => 'I would like to do',
                    'class' => FunctionEntity::class,
                    'mapped' => false,
                    'multiple' => true,
                    'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('fe')
                        ->where("fe.user_pickable = :up")
                        ->setParameter('up', true);
                    },
                    'attr' => [
                        'class' => 'selectpicker',
                        'data-live-search' => 'true',
                        'data-width' => '40%',
                        'data-style' => 'btn-dropdown',
                    ]
                ]);
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // TODO: Config option for setting the APPLICANT state for an
            // extra registration step.

            $entityManager->persist($user);
            if ($functions = $form['function']?->getData()) {
                foreach ($functions as $function) {
                    $pf = new PersonFunction();
                    $pf->setPerson($user);
                    $pf->setFunction($function);
                    $pf->setFromDate(new \DateTime());
                    $user->addPersonFunction($pf);
                    $entityManager->persist($pf);
                }
            }
            $first_org = $entityManager->getRepository(Organization::class)->getInternalOrganization();
            $first_role = $entityManager->getRepository(Role::class)->getDefaultRole();
            $pro = new PersonRoleOrganization();
            $pro->setPerson($user);
            $pro->setOrganization($first_org);
            $pro->setRole($first_role);
            $entityManager->persist($pro);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $mailfrom = $this->params->get('mailfrom');
            $mailname = $this->params->get('mailname');
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address($mailfrom, $mailname))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->textTemplate('registration/confirmation_email.html.twig')
            );
            // do anything else you need here, like send an email
            $this->addFlash('info', 'Thank you. Check your mailbox for verification mail');
            return $this->redirectToRoute('index');
        }
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/verify/email", name="app_verify_email")
     */
    public function verifyUserEmail(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets Person::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $this->getUser());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
    }
}
