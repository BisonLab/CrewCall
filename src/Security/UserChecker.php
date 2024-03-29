<?php

namespace App\Security;

use App\Entity\Person as AppPerson;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppPerson) {
            return;
        }

        if (!$user->isEnabled()) {
            // the message passed to this exception is meant to be displayed to the user
            throw new CustomUserMessageAccountStatusException('You are disabled');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof AppPerson) {
            return;
        }
        if (!$user->isEnabled()) {
            throw new AccountExpiredException('...');
        }
    }
}
