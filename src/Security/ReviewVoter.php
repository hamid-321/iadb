<?php

namespace App\Security;

use App\Entity\Review;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ReviewVoter extends Voter
{
    const VIEW = 'view_review';
    const CREATE = 'create_review';
    const EDIT = 'edit_review';
    const DELETE = 'delete_review';
   
    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE])) {
            return false;
        }

        // For create, review doesnt exist yet, so allow it
        if ($attribute === self::CREATE) {
            return true;
        }

        // otherwise must be a review
        if (!$subject instanceof Review) {
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
            self::VIEW => $this->canViewReview($subject, $user),
            self::CREATE => $this->canCreateReview($subject, $user),
            self::EDIT => $this->canEditReview($subject, $user, $token),
            self::DELETE => $this->canDeleteReview($subject, $user, $token),
            default => false,
        };
    }

    private function canViewReview(Review $review, User $user): bool
    {
        return true;
    }

    private function canCreateReview(mixed $subject, User $user): bool
    {
        return true;
    }

    private function canEditReview(Review $review, User $user, TokenInterface $token): bool
    {
        if ($user->getId() === $review->getReviewer()?->getId()) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_MODERATOR'])) {
            return true;
        }

        return false;
    }

    private function canDeleteReview(Review $review, User $user, TokenInterface $token): bool
    {
        if ($user->getId() === $review->getReviewer()?->getId()) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_MODERATOR'])) {
            return true;
        }

        return false;
    }
}