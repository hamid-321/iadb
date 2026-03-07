<?php

namespace App\Controller\api\v1;

use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController as Rest;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Delete;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Controller\api\v1\APIUtilities;
use App\Service\AverageRatingService;
use App\Form\api\ReviewAPIType;
use App\Entity\Review;

class ReviewAPIController extends Rest
{

/*******************************************************************************
 * 
 * GET METHODS
 * 
 ******************************************************************************/

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
        foreach ($reviews as $review)
        {
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
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Review not found'], Response::HTTP_NOT_FOUND);
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

/*******************************************************************************
 * 
 * POST METHODS
 * 
 ******************************************************************************/

    #[Post('/api/v1/reviews', name: 'api_review_create')]
    public function createReview(Request $request, AlbumRepository $albumRepository, EntityManagerInterface $entityManager, AverageRatingService $averageRatingService): View
    {
        //get user from jwt token
        $user = $this->getUser();

        //if the user is not found, return an unauthorised response
        if (!$user)
        {
            $view = View::create(['code' => Response::HTTP_UNAUTHORIZED, 'errors' => 'Must be logged in to create a review'], Response::HTTP_UNAUTHORIZED);
            return $view;
        }

        //create the review form and get the data from the json request
        $review = new Review();
        $form = $this->createForm(ReviewAPIType::class, $review);
        $data = json_decode($request->getContent(), true);

        $form->submit($data);

        //if the form is valid, create the review and respond with the review id
        if ($form->isSubmitted() && $form->isValid())
        {
            
            $album = $albumRepository->find($data['album_id']);
            if (!$album)
            {
                $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Album does not exist'], Response::HTTP_NOT_FOUND);
                return $view;
            }
            
            $review->setAlbum($album);
            $review->setReviewer($user);
            $review->setTimestamp(new \DateTime());

            $album->addReview($review);

            $averageRatingService->calculateAverageRating($album);

            $entityManager->persist($review);
            $entityManager->flush();

            $responseData = [
                'message' => 'Review created successfully',
                'links' => [
                    'self' => $this->generateUrl('api_review_detail', ['r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'album' => $this->generateUrl('api_album_detail', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];

            $reviewUrl = $this->generateUrl('api_review_detail', ['r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            $view = View::create($responseData, Response::HTTP_CREATED);
            // add the location of the new review to the header
            $view->setHeader('Location', $reviewUrl);
            return $view;
        }
        //if the form is not valid, return the errors

        $errors = [];
        foreach ($form->getErrors(true) as $error)
        {
            $errors[] = $error->getMessage();
        }

        $view = View::create(['code' => Response::HTTP_BAD_REQUEST, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        return $view;
    }

/*******************************************************************************
 * 
 * PUT METHODS
 * 
 ******************************************************************************/

    #[Put('/api/v1/reviews/{r_id}', name: 'api_review_update')]
    public function updateReview(int $r_id, Request $request, ReviewRepository $reviewRepository, EntityManagerInterface $entityManager, AverageRatingService $averageRatingService): View
    {
        $user = $this->getUser();
        $review = $reviewRepository->find($r_id);

        //if the user is not found, return an unauthorised response
        if (!$user)
        {
            $view = View::create(['code' => Response::HTTP_UNAUTHORIZED, 'errors' => 'Must be logged in to edit a review'], Response::HTTP_UNAUTHORIZED);
            return $view;
        }

        //if the review is not found, return a not found response
        if (!$review)
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Review not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }
        
        if ($review->getReviewer() !== $user)
        {
            $view = View::create(['code' => Response::HTTP_FORBIDDEN, 'errors' => 'Not authorised to edit this review'], Response::HTTP_FORBIDDEN);
            return $view;
        }

        //create the review form and get the data from the json request
        $form = $this->createForm(ReviewAPIType::class, $review);
        $data = json_decode($request->getContent(), true);

        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid())
        {
            $review->setTimestamp(new \DateTime());

            $entityManager->persist($review);
            $entityManager->flush();

            $averageRatingService->calculateAverageRating($review->getAlbum());

            $responseData = [
                'message' => 'Review updated successfully',
                'links' => [
                    'self' => $this->generateUrl('api_review_detail', ['r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'album' => $this->generateUrl('api_album_detail', ['a_id' => $review->getAlbum()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];

            $view = View::create($responseData, Response::HTTP_OK);
            return $view;
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error)
        {
            $errors[] = $error->getMessage();
        }

        $view = View::create(['code' => Response::HTTP_BAD_REQUEST, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        return $view;
    }

/*******************************************************************************
 * 
 * DELETE METHODS
 * 
 ******************************************************************************/

    #[Delete('/api/v1/reviews/{r_id}', name: 'api_review_delete')]
    public function deleteReview(int $r_id, Request $request, ReviewRepository $reviewRepository, EntityManagerInterface $entityManager, AverageRatingService $averageRatingService): View
    {
        $user = $this->getUser();
        $review = $reviewRepository->find($r_id);
        
        if (!$user)
        {
            $view = View::create(['code' => Response::HTTP_UNAUTHORIZED, 'errors' => 'Must be logged in to delete a review'], Response::HTTP_UNAUTHORIZED);
            return $view;
        }
        
        if (!$review)
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Review not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }
        
        if ($review->getReviewer() !== $user)
        {
            $view = View::create(['code' => Response::HTTP_FORBIDDEN, 'errors' => 'Not authorised to delete this review'], Response::HTTP_FORBIDDEN);
            return $view;
        }
        
        $album = $review->getAlbum();

        //remove the review and flush so avg rating calc picks up the revmoved review
        $entityManager->remove($review);
        $entityManager->flush();

        //calculate the new average rating and persist the album
        $averageRatingService->calculateAverageRating($album);
        $entityManager->persist($album);
        $entityManager->flush();

        $view = View::create(null, Response::HTTP_NO_CONTENT);
        return $view;
    }
}