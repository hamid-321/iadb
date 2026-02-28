<?php

namespace App\Controller\api\v1;

use App\Repository\AlbumRepository;
use FOS\RestBundle\Controller\AbstractFOSRestController as Rest;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use FOS\RestBundle\Controller\Annotations\Get;

class AlbumAPIController extends Rest
{
    #[Get('/api/v1/albums', name: 'api_albums_list')]
    public function getAlbumsAction(AlbumRepository $albumRepository, PaginatorInterface $paginator, Request $request): View
    {
        //fetch query from repository
        $query = $albumRepository->getAPIPaginationQuery();

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
        $items = $pagination->getItems();
        $formattedItems = [];
        foreach ($items as $album) {
            $cleanTracks = $this->tidyTrackList($album->getTrackList());

            $formattedItems[] = [
                'id' => $album->getId(),
                'title' => $album->getTitle(),
                'artist' => $album->getArtist(),
                'tracks' => $cleanTracks,
                'averageRating' => ($album->getAverageRating() !== null) ? round($album->getAverageRating(), 1) : 0,
            ];
        }

        //prepare data for response
        $responseData = [
            'data' => $formattedItems,
            'meta' => [
                'current_page' => $pagination->getCurrentPageNumber(),
                'total_items' => $pagination->getTotalItemCount(),
                'items_per_page' => $pagination->getItemNumberPerPage(),
                'total_pages' => (int)ceil($pagination->getTotalItemCount() / $pagination->getItemNumberPerPage()),
            ]
        ];
        //create and return the view
        $view = View::create($responseData, Response::HTTP_OK);

        return $view;
    }


    #[Get('/api/v1/albums/{id}', name: 'api_album_detail')]
    public function getAlbumDetailAction(int $id, AlbumRepository $albumRepository): View
    {
        //fetch the album from the repository
        $album = $albumRepository->find($id);

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
            'tracks' => $cleanTracks,
            'averageRating' => ($album->getAverageRating() !== null) ? round($album->getAverageRating(), 1) : 0,
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