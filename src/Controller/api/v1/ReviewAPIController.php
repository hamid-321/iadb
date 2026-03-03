<?php

namespace App\Controller\api\v1;

use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use FOS\RestBundle\Controller\AbstractFOSRestController as Rest;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use FOS\RestBundle\Controller\Annotations\Get;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Controller\api\v1\APIUtilities;

class ReviewAPIController extends Rest
{
    #[Get('/api/v1/reviews', name: 'api_reviews_list')]
    public function getReviewsList(ReviewRepository $reviewRepository, PaginatorInterface $paginator, Request $request, APIUtilities $apiUtilities): View
    {
        $sortBy = $request->query->get('sortBy', 'timestamp');
        $sortDirection = $request->query->get('sortDirection', 'desc');

        //fetch query from repository
        $query = $reviewRepository->getAPIPaginationQuery($sortBy, $sortDirection);

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('pageSize', 10);

        //add guardrails for page and limit
        if ($page <= 0) 
        {
            $page = 1;
        }

        if ($limit <= 0 || $limit > 100) 
        {
            $limit = 10;
        }

        // apply pagination to query
        $pagination = $paginator->paginate(
            $query,
            $page,
            $limit
        );

        $reviews = $pagination->getItems();
        $formattedReviewsData = [];
        foreach ($reviews as $review) {
            $formattedReviewsData[] = [
                'id' => $review->getId(),
                'album_id' => $review->getAlbum()->getId(),
                'reviewer_username' => $review->getReviewer()->getUsername(),
                'review_text' => $review->getReviewText(),
                'rating' => $review->getRating(),
                'timestamp' => $review->getTimestamp(),
                'links' => [
                    'self' => $this->generateUrl('api_review_detail', ['r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'album' => $this->generateUrl('api_album_detail', ['a_id' => $review->getAlbum()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'reviewer' => $this->generateUrl('api_user_detail', ['u_id' => $review->getReviewer()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];
        }

        $responseData = [
            'data' => $formattedReviewsData,
            'meta' => $apiUtilities->getPaginationMeta($pagination, $request, 'api_reviews_list')
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/reviews/{r_id}', name: 'api_review_detail')]
    public function getReviewDetail(int $r_id, ReviewRepository $reviewRepository): View
    {
        $review = $reviewRepository->find($r_id);

        if (!$review) 
        {
            $view = View::create(['error' => 'Review not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $responseData = [
            'id' => $review->getId(),
            'album_id' => $review->getAlbum()->getId(),
            'reviewer_username' => $review->getReviewer()->getUsername(),
            'review_text' => $review->getReviewText(),
            'rating' => $review->getRating(),
            'timestamp' => $review->getTimestamp(),
            'links' => [
                'self' => $this->generateUrl('api_review_detail', ['r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'album' => $this->generateUrl('api_album_detail', ['a_id' => $review->getAlbum()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviewer' => $this->generateUrl('api_user_detail', ['u_id' => $review->getReviewer()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }
}