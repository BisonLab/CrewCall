<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use App\Entity\Person;

#[AsCommand(
    name: 'crewcall:user:send-passwordmail',
    description: 'Sends a passord reset email to the specified username'
)]
class SendPasswordEmailCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');

        if (!$user = $this->entityManager->getRepository(Person::class)->findOneBy(['username' => $username])) {
            $io->error('Error, did not find the user');
            return 1;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $io->error("Error creating the send password token.\n" . $e->getReason());
            return 1;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->params->get('mailfrom'), $this->params->get('mailname')))
            ->to($user->getEmail())
            ->subject('Your password reset request')
            ->textTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
            ])
        ;

        $this->mailer->send($email);

        $io->success('You sent a password reset email to ' . (string)$user
            . " with address " . $user->getEmail());

        return 0;
    }
}
