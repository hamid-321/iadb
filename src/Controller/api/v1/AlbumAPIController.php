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

class AlbumAPIController extends Rest
{
    #[Get('/api/v1/albums', name: 'api_albums_list')]
    public function getAlbumsList(AlbumRepository $albumRepository, PaginatorInterface $paginator, Request $request): View
    {
        $albumString = $request->query->get('album', '');
        $artistString = $request->query->get('artist', '');
        $genreString = $request->query->get('genre', '');
        $minRating = $request->query->get('minRating');
        $maxRating = $request->query->get('maxRating');
        $sortBy = $request->query->get('sortBy', 'id');
        $sortOrder = $request->query->get('sortOrder', 'asc');

        if (!is_float($minRating)) {
            $view = View::create(['error' => 'Min rating must be a number'], Response::HTTP_BAD_REQUEST);
            return $view;
        }
        if (!is_float($maxRating)) {
            $view = View::create(['error' => 'Max rating must be a number'], Response::HTTP_BAD_REQUEST);
            return $view;
        }

        //fetch query from repository
        $query = $albumRepository->getAPIPaginationQuery($albumString, $artistString, $genreString, $minRating, $maxRating, $sortBy, $sortOrder);

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
        foreach ($albums as $album) {
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
            ];
        }

        //prepare data for response
        $responseData = [
            'data' => $formattedAlbumsData,
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


    #[Get('/api/v1/albums/{a_id}', name: 'api_album_detail')]
    public function getAlbumsDetail(int $a_id, AlbumRepository $albumRepository, Request $request): View
    {
        //fetch the album from the repository
        $album = $albumRepository->find($a_id);

        //if the album is not found, return a 404 error
        if (!$album) 
        {
            $view = View::create(['error' => 'Album not found'], Response::HTTP_NOT_FOUND);
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
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    #[Get('/api/v1/albums/{a_id}/reviews', name: 'api_album_reviews_list')]
    public function getAlbumsReviews(int $a_id, AlbumRepository $albumRepository, ReviewRepository $reviewRepository, PaginatorInterface $paginator, Request $request): View
    {
        $album = $albumRepository->find($a_id);

        //if the album is not found, return a 404 error
        if (!$album) 
        {
            $view = View::create(['error' => 'Album not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $sortBy = $request->query->get('sortBy', 'timestamp');
        $sortOrder = $request->query->get('sortOrder', 'desc');

        //fetch the reviews for the album
        $query = $reviewRepository->getAPIPaginationByAlbumQuery($album, $sortBy, $sortOrder);

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
        ];

        //prepare data for response
        $formattedReviewsData = [];
        foreach ($reviews as $review) {
            $reviewer = $review->getReviewer();
            $formattedReviewsData[] = [
                'id' => $review->getId(),
                'reviewer_username' => $reviewer->getUsername(),
                'review_text' => $review->getReviewText(),
                'rating' => $review->getRating(),
                'timestamp' => $review->getTimestamp(),
            ];
        }

        $responseData = [
            'album' => $albumData,
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

    #[Get('/api/v1/albums/{a_id}/reviews/{r_id}', name: 'api_album_review_detail')]
    public function getAlbumsReviewsDetail(int $a_id, int $r_id, AlbumRepository $albumRepository, ReviewRepository $reviewRepository): View
    {
        $album = $albumRepository->find($a_id);

        //if the album is not found, return a 404 error
        if (!$album) 
        {
            $view = View::create(['error' => 'Album not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        $review = $reviewRepository->find($r_id);

        //if the review is not found, return a 404 error
        if (!$review) 
        {
            $view = View::create(['error' => 'Review not found'], Response::HTTP_NOT_FOUND);
            return $view;
        }

        //if the review is not for this album, return a 404 error
        if ($review->getAlbum() !== $album) 
        {
            $view = View::create(['error' => 'Review is not for this album'], Response::HTTP_NOT_FOUND);
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
        ];

        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }

    //utility function to clean the track list and format as an array
    private function tidyTrackList(?string $rawTrackList): array
    {
        if ($rawTrackList === null || $rawTrackList === '') {
            return [];
        }
        $tracks = preg_split('/(\r\n|\n)/', $rawTrackList, -1, PREG_SPLIT_NO_EMPTY);

        return array_map('trim', $tracks);
    }
}