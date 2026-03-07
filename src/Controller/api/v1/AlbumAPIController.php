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

class AlbumAPIController extends Rest
{

/*******************************************************************************
 * 
 * GET METHODS
 * 
 ******************************************************************************/

    #[Get('/api/v1/albums', name: 'api_albums_list')]
    public function getAlbumsList(AlbumRepository $albumRepository, PaginatorInterface $paginator, Request $request, APIUtilities $apiUtilities): View
    {
        $albumString = $request->query->get('album', '');
        $artistString = $request->query->get('artist', '');
        $genreString = $request->query->get('genre', '');
        $minRatingString = $request->query->get('minRating');
        $maxRatingString = $request->query->get('maxRating');
        $sortBy = $request->query->get('sortBy', 'id');
        $sortDirection = $request->query->get('sortDirection', 'asc');


        $minRating = ($minRatingString !== null && $minRatingString !== '' && is_numeric($minRatingString)) ? (float) $minRatingString : null;
        $maxRating = ($maxRatingString !== null && $maxRatingString !== '' && is_numeric($maxRatingString)) ? (float) $maxRatingString : null;

        if ($minRatingString !== null && $minRatingString !== '' && !is_numeric($minRatingString))
        {
            $view = View::create(['code' => Response::HTTP_BAD_REQUEST, 'errors' => 'Min rating must be a number'], Response::HTTP_BAD_REQUEST);
            return $view;
        }

        if ($maxRatingString !== null && $maxRatingString !== '' && !is_numeric($maxRatingString))
        {
            $view = View::create(['code' => Response::HTTP_BAD_REQUEST, 'errors' => 'Max rating must be a number'], Response::HTTP_BAD_REQUEST);
            return $view;
        }

        //fetch query from repository
        $query = $albumRepository->getAPIPaginationQuery($albumString, $artistString, $genreString, $minRating, $maxRating, $sortBy, $sortDirection);

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

        //clean the track list and format as an array, also prepare data for response
        $albums = $pagination->getItems();
        $formattedAlbumsData = [];
        foreach ($albums as $album)
        {
            //clean the track list
            $cleanTracks = $this->tidyTrackList($album->getTrackList());

            $formattedAlbumsData[] = [
                'id' => $album->getId(),
                'title' => $album->getTitle(),
                'artist' => $album->getArtist(),
                'genre' => $album->getGenre(),
                'tracks' => $cleanTracks,
                'cover_image' => $album->getCoverName() 
                                 ? $request->getSchemeAndHttpHost() . '/images/albumCovers/' . $album->getCoverName() 
                                 : null,
                'averageRating' => ($album->getAverageRating() !== null) ? round($album->getAverageRating(), 1) : null,
                'links' => [
                    'self' => $this->generateUrl('api_album_detail', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'reviews' => $this->generateUrl('api_album_reviews_list', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];
        }

        //prepare data for response
        $responseData = [
            'data' => $formattedAlbumsData,
            'meta' => $apiUtilities->getPaginationMeta($pagination, $request, 'api_albums_list')
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }


    #[Get('/api/v1/albums/{a_id}', name: 'api_album_detail')]
    public function getAlbumsDetail(int $a_id, AlbumRepository $albumRepository, Request $request): View
    {
        //fetch the album from the repository
        $album = $albumRepository->find($a_id);

        //if the album is not found, return a 404 error
        if (!$album) 
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Album not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $cleanTracks = $this->tidyTrackList($album->getTrackList());

        $responseData = [
            'id' => $album->getId(),
            'title' => $album->getTitle(),
            'artist' => $album->getArtist(),
            'genre' => $album->getGenre(),
            'tracks' => $cleanTracks,
            'cover_image' => $album->getCoverName() 
                             ? $request->getSchemeAndHttpHost() . '/images/albumCovers/' . $album->getCoverName() 
                             : null,
            'averageRating' => ($album->getAverageRating() !== null) ? round($album->getAverageRating(), 1) : null,
            'links' => [
                'self' => $this->generateUrl('api_album_detail', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviews' => $this->generateUrl('api_album_reviews_list', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/albums/{a_id}/reviews', name: 'api_album_reviews_list')]
    public function getAlbumsReviews(int $a_id, AlbumRepository $albumRepository, ReviewRepository $reviewRepository, PaginatorInterface $paginator, Request $request, APIUtilities $apiUtilities): View
    {
        $album = $albumRepository->find($a_id);

        //if the album is not found, return a 404 error
        if (!$album) 
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Album not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $sortBy = $request->query->get('sortBy', 'timestamp');
        $sortDirection = $request->query->get('sortDirection', 'desc');

        //fetch the reviews for the album
        $query = $reviewRepository->getAPIPaginationByAlbumQuery($album, $sortBy, $sortDirection);

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

        $pagination = $paginator->paginate(
            $query,
            $page,
            $limit
        );

        $reviews = $pagination->getItems();

        $cleanTracks = $this->tidyTrackList($album->getTrackList());

        $albumData = [
            'id' => $album->getId(),
            'title' => $album->getTitle(),
            'artist' => $album->getArtist(),
            'genre' => $album->getGenre(),
            'tracks' => $cleanTracks,
            'cover_image' => $album->getCoverName() 
                             ? $request->getSchemeAndHttpHost() . '/images/albumCovers/' . $album->getCoverName() 
                             : null,
            'averageRating' => ($album->getAverageRating() !== null) ? round($album->getAverageRating(), 1) : null,
            'links' => [
                'self' => $this->generateUrl('api_album_detail', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviews' => $this->generateUrl('api_album_reviews_list', ['a_id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];

        //prepare data for response
        $formattedReviewsData = [];
        foreach ($reviews as $review)
        {
            $reviewer = $review->getReviewer();
            $formattedReviewsData[] = [
                'id' => $review->getId(),
                'reviewer_username' => $reviewer->getUsername(),
                'review_text' => $review->getReviewText(),
                'rating' => $review->getRating(),
                'timestamp' => $review->getTimestamp(),
                'links' => [
                    'self' => $this->generateUrl('api_album_review_detail', ['a_id' => $a_id, 'r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ];
        }

        $responseData = [
            'album' => $albumData,
            'data' => $formattedReviewsData,
            'meta' => $apiUtilities->getPaginationMeta($pagination, $request, 'api_album_reviews_list', ['a_id' => $a_id])
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/albums/{a_id}/reviews/{r_id}', name: 'api_album_review_detail')]
    public function getAlbumsReviewsDetail(int $a_id, int $r_id, AlbumRepository $albumRepository, ReviewRepository $reviewRepository): View
    {
        $album = $albumRepository->find($a_id);

        //if the album is not found, return a 404 error
        if (!$album) 
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Album not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $review = $reviewRepository->find($r_id);

        //if the review is not found, return a 404 error
        if (!$review) 
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Review not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        //if the review is not for this album, return a 404 error
        if ($review->getAlbum() !== $album) 
        {
            $view = View::create(['code' => Response::HTTP_NOT_FOUND, 'errors' => 'Review is not for this album'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $reviewer = $review->getReviewer();

        $responseData = [
            'id' => $review->getId(),
            'album_id' => $album->getId(),
            'reviewer_username' => $reviewer->getUsername(),
            'review_text' => $review->getReviewText(),
            'rating' => $review->getRating(),
            'timestamp' => $review->getTimestamp(),
            'links' => [
                'self' => $this->generateUrl('api_album_review_detail', ['a_id' => $a_id, 'r_id' => $review->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'album' => $this->generateUrl('api_album_detail', ['a_id' => $a_id], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviews' => $this->generateUrl('api_album_reviews_list', ['a_id' => $a_id], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviewer' => $this->generateUrl('api_user_detail', ['u_id' => $reviewer->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    //utility function to clean the track list and format as an array
    private function tidyTrackList(?string $rawTrackList): array
    {
        if ($rawTrackList === null || $rawTrackList === '')
        {
            return [];
        }
        $tracks = preg_split('/(\r\n|\n)/', $rawTrackList, -1, PREG_SPLIT_NO_EMPTY);

        return array_map('trim', $tracks);
    }
}