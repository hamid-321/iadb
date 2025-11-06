<?php

namespace App\Service;

use App\Entity\Album;
use Doctrine\ORM\EntityManagerInterface;

class AverageRatingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function calculateAverageRating(Album $album): void
    {
        $reviews = $album->getReviews();

        if ($reviews->isEmpty()) {
            $album->setAverageRating(null);
            $this->entityManager->flush();
            return;
        }

        $totalRating = 0;
        $reviewCount = 0;

        foreach ($reviews as $review) {
            if ($review->getRating() !== null) {
            $totalRating += $review->getRating();
            $reviewCount++;
            }
        }

        if ($reviewCount > 0) {
            $averageRating = round ($totalRating / $reviewCount, 1);
            $album->setAverageRating($averageRating);
        } 
        else {
            $album->setAverageRating(null);
        }

        $this->entityManager->persist($album);
        //no need to flush, controller already will flush after this has been called
    }
}