<?php

namespace App\Security;

use App\Entity\Album;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AlbumVoter extends Voter
{
    const VIEW = 'view_album';
    const CREATE = 'create_album';
    const EDIT = 'edit_album';
    const DELETE = 'delete_album';
   
    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE])) {
            return false;
        }

        // For create, album doesnt exist yet, so allow it
        if ($attribute === self::CREATE) {
            return true;
        }

        // otherwise must be a album
        if (!$subject instanceof Album) {
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
            self::VIEW => $this->canViewAlbum($subject, $user),
            self::CREATE => $this->canCreateAlbum($subject, $user),
            self::EDIT => $this->canEditAlbum($subject, $user, $token),
            self::DELETE => $this->canDeleteAlbum($subject, $user, $token),
            default => false,
        };
    }

    private function canViewAlbum(Album $album, User $user): bool
    {
        return true;
    }

    private function canCreateAlbum(mixed $subject, User $user): bool
    {
        return true;
    }

    private function canEditAlbum(Album $album, User $user, TokenInterface $token): bool
    {
        if ($user->getId() === $album->getAddedBy()?->getId()) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false;
    }

    private function canDeleteAlbum(Album $album, User $user, TokenInterface $token): bool
    {

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return false;
    }
}