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
    const ADMIN = 'user_admin';
   
    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::ADMIN])) {
            return false;
        }

        // For create, user doesnt exist yet, so allow it
        if ($attribute === self::CREATE) {
            return true;
        }

        // For admin requests allow access
        if ($attribute === self::ADMIN) {
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

        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');
            return false;
        }

        // Prevent an admin or any user from deleting themself
        if ($attribute === self::DELETE) {
            $canDelete = $this->canDeleteUser($subject, $user, $token, $vote);
            if ($canDelete) {
                return true;
            }
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            $vote?->addReason('The user is an admin.');
            return true;
        }


        return match ($attribute) {
            self::VIEW => $this->canViewUser($subject, $user, $token),
            self::CREATE => $this->canCreateUser($subject, $user, $token),
            self::EDIT => $this->canEditUser($subject, $user, $token),
            self::DELETE => $this->canDeleteUser($subject, $user, $token, $vote),
            self::ADMIN => $this->canAdminUser($user,$token),
            default => false,
        };
    }

    private function canViewUser(User $subject, User $user, TokenInterface $token): bool
    {
        if ($user->getId() === $subject->getId()) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false;
    }

    private function canCreateUser(mixed $subject, User $user, TokenInterface $token): bool
    {
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false;//only admin can create users
    }

    private function canEditUser(User $subject, User $user, TokenInterface $token): bool
    {
        if ($user->getId() === $subject->getId()) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false; //only allow user to edit their own profile
    }

    private function canDeleteUser(User $subject, User $user, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($user->getId() === $subject->getId()) {
            $vote?->addReason('The user cannot delete themselves.');
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false;//only admin can delete users
    }

    private function canAdminUser(User $user, TokenInterface $token): bool
    {
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false;//used to block certain fields in the edit form from non admins
    }
}