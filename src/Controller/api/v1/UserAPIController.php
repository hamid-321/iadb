<?php

namespace App\Controller\api\v1;

use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use FOS\RestBundle\Controller\AbstractFOSRestController as Rest;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use FOS\RestBundle\Controller\Annotations\Get;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Controller\api\v1\APIUtilities;

class UserAPIController extends Rest
{

    #[Get('/api/v1/users', name: 'api_users_list')]
    public function getUserList(UserRepository $userRepository, PaginatorInterface $paginator, Request $request, APIUtilities $apiUtilities): View
    {
        $sortBy = $request->query->get('sortBy', 'id');
        $sortDirection = $request->query->get('sortDirection', 'asc');

        //fetch query from repository
        $query = $userRepository->getPaginationQuery($sortBy, $sortDirection);

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

        $users = $pagination->getItems();

        $formattedUsersData = [];
        foreach ($users as $user) {
            $formattedUsersData[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'links' => [
                    'self' => $this->generateUrl('api_user_detail', ['u_id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'reviews' => $this->generateUrl('api_user_reviews_list', ['u_id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];
        }

        $responseData = [
            'data' => $formattedUsersData,
            'meta' => $apiUtilities->getPaginationMeta($pagination, $request, 'api_users_list')
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/users/{u_id}', name: 'api_user_detail')]
    public function getUserDetail(int $u_id, UserRepository $userRepository): View
    {
        $user = $userRepository->find($u_id);

        if (!$user) 
        {
            $view = View::create(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $responseData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'links' => [
                'self' => $this->generateUrl('api_user_detail', ['u_id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviews' => $this->generateUrl('api_user_reviews_list', ['u_id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/users/{u_id}/reviews', name: 'api_user_reviews_list')]
    public function getUserReviewsList(int $u_id, UserRepository $userRepository, ReviewRepository $reviewRepository, PaginatorInterface $paginator, Request $request, APIUtilities $apiUtilities): View
    {
        $user = $userRepository->find($u_id);

        if (!$user) 
        {
            $view = View::create(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $sortBy = $request->query->get('sortBy', 'timestamp');
        $sortDirection = $request->query->get('sortDirection', 'desc');

        $query = $reviewRepository->getAPIPaginationByUserQuery($user, $sortBy, $sortDirection);

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('pageSize', 10);

        //add guardrails for page and limit
        if ($page <= 0) {
            $page = 1;
        }
        if ($limit <= 0 || $limit > 100) {
            $limit = 10;
        }

        $pagination = $paginator->paginate(
            $query,
            $page,
            $limit
        );

        $reviews = $pagination->getItems();

        $userData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'links' => [
                'self' => $this->generateUrl('api_user_detail', ['u_id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviews' => $this->generateUrl('api_user_reviews_list', ['u_id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];

        //prepare data for response
        $formattedReviewsData = [];
        foreach ($reviews as $review) {
            $formattedReviewsData[] = [
                'id' => $review->getId(),
                'album_id' => $review->getAlbum()->getId(),
                'album_title' => $review->getAlbum()->getTitle(),
                'review_text' => $review->getReviewText(),
                'rating' => $review->getRating(),
                'timestamp' => $review->getTimestamp(),
                'links' => [
                    'self' => $this->generateUrl('api_review_detail', ['r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'album' => $this->generateUrl('api_album_detail', ['a_id' => $review->getAlbum()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];
        }

        $responseData = [
            'user' => $userData,
            'data' => $formattedReviewsData,
            'meta' => $apiUtilities->getPaginationMeta($pagination, $request, 'api_user_reviews_list', ['u_id' => $u_id])
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }
}