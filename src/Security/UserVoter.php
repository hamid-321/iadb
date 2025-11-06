<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    const VIEW = 'view_user';
    const CREATE = 'create_user';
    const EDIT = 'edit_user';
    const DELETE = 'delete_user';
   
    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE])) {
            return false;
        }

        // For create, user doesnt exist yet, so allow it
        if ($attribute === self::CREATE) {
            return true;
        }

        // otherwise must be a user
        if (!$subject instanceof User) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            $vote?->addReason('The user is an admin.');
            return true;
        }

        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canViewUser($subject, $user),
            self::CREATE => $this->canCreateUser($subject, $user),
            self::EDIT => $this->canEditUser($subject, $user, $token),
            self::DELETE => $this->canDeleteUser($subject, $user, $token),
            default => false,
        };
    }

    private function canViewUser(User $subject, User $user): bool
    {
        if ($user->getId() === $subject->getId()) {
            return true;
        }
    }

    private function canCreateUser(mixed $subject, User $user): bool
    {
        return false;//only admin can create users
    }

    private function canEditUser(User $subject, User $user, TokenInterface $token): bool
    {
        if ($user->getId() === $subject->getId()) {
            return true;
        }

        return false; //only allow user to edit their own profile
    }

    private function canDeleteUser(User $subject, User $user, TokenInterface $token): bool
    {
        return false;//only admin can delete users
    }
}