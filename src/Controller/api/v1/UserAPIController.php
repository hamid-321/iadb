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

class UserAPIController extends Rest
{
    #[Get('/api/v1/users', name: 'api_users_list')]
    public function getUserList(UserRepository $userRepository, PaginatorInterface $paginator, Request $request): View
    {
        //fetch query from repository
        $query = $userRepository->getPaginationQuery();

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
            ];
        }

        $responseData = [
            'data' => $formattedUsersData,
            'meta' => [
                'current_page' => $pagination->getCurrentPageNumber(),
                'total_items' => $pagination->getTotalItemCount(),
                'items_per_page' => $pagination->getItemNumberPerPage(),
                'total_pages' => $pagination->getPageCount(),
            ]
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
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/users/{u_id}/reviews', name: 'api_user_reviews_list')]
    public function getUserReviewsList(int $u_id, UserRepository $userRepository, ReviewRepository $reviewRepository, PaginatorInterface $paginator, Request $request): View
    {
        $user = $userRepository->find($u_id);

        if (!$user) 
        {
            $view = View::create(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $query = $reviewRepository->getPaginationByUserQuery($user);

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
            ];
        }

        $responseData = [
            'user' => $userData,
            'data' => $formattedReviewsData,
            'meta' => [
                'current_page' => $pagination->getCurrentPageNumber(),
                'total_items' => $pagination->getTotalItemCount(),
                'items_per_page' => $pagination->getItemNumberPerPage(),
                'total_pages' => $pagination->getPageCount(),
            ],
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }
}