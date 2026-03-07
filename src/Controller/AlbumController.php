<?php

namespace App\Controller;

use App\Entity\Album;
use App\Form\AlbumType;
use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\MusicBrainzAPIService;

final class AlbumController extends AbstractController
{
    //no restrictions, anyone can view the albums
    #[Route('/album', name: 'app_album_index', methods: ['GET'])]
    public function index(Request $request, AlbumRepository $albumRepository, PaginatorInterface $paginator): Response
    {
        $query = $albumRepository->getPaginationQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            9,
            ['defaultSortFieldName' => 'lower_title', 'defaultSortDirection' => 'asc']
        );
        return $this->render('album/index.html.twig', [
            'albums' => $pagination,
        ]);
    }

    
    #[Route('/album/search', name: 'app_album_search', methods: ['GET'])]
    public function search(Request $request, AlbumRepository $albumRepository, PaginatorInterface $paginator): Response
    {
        $albumString = $request->query->get('album', '');
        $artistString = $request->query->get('artist', '');
        $genreString = $request->query->get('genre', '');
        $min  = $request->query->get('minRating');
        $max  = $request->query->get('maxRating');

        // if not a valid number, set to null
        $minRating = ($min === null || $min === '') ? null : (float) $min;
        $maxRating = ($max === null || $max === '') ? null : (float) $max;

        $query = $albumRepository->getPaginationSearchQuery($albumString, $artistString, $genreString, $minRating, $maxRating);

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            9,
            ['defaultSortFieldName' => 'lower_title', 'defaultSortDirection' => 'asc']
        );

        return $this->render('album/search.html.twig', [
            'albums'    => $pagination,
            'album'     => $albumString,
            'artist'    => $artistString,
            'genre'     => $genreString,
            'minRating' => $min,
            'maxRating' => $max,
        ]);
    }
    
    #[Route('/album/new', name: 'app_album_new', methods: ['GET', 'POST'])]
    #[IsGranted('create_album')]
    public function new(Request $request, EntityManagerInterface $entityManager, MusicBrainzAPIService $musicBrainzAPIService): Response
    {
        $user = $this->getUser();
        $album = new Album();
        $album->setAddedBy($user);

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->get('autofill')->isClicked())
        {
            $musicBrainzAutofillData = $musicBrainzAPIService->albumAutofillAction($album->getTitle(), $album->getArtist());

            if ($musicBrainzAutofillData) 
            {
                $album->setTrackList($musicBrainzAutofillData['tracks']);
                $album->setTitle($musicBrainzAutofillData['title']);
                $album->setArtist($musicBrainzAutofillData['artist']);

                $this->addFlash('success', 'Album autofill data has been added.');
            }
            else
            {
                $this->addFlash('error', 'No album autofill data found.');
            }

            //recreate the form with the new data
            return $this->render('album/new.html.twig', [
                'form' => $this->createForm(AlbumType::class, $album)->createView(),
            ]);
        }

        //if successful, create and redirect to the new album
        if ($form->isSubmitted() && $form->isValid())
        {
            $entityManager->persist($album);
            $entityManager->flush();

            $this->addFlash('success', 'The album has been created.');
            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/new.html.twig', [
            'album' => $album,
            'form' => $form,
        ]);
    }

    //once again, no restrictions, anyone can view an album
    #[Route('/album/{id}', name: 'app_album_show', methods: ['GET'])]
    public function show(Request $request, Album $album, ReviewRepository $reviewRepository, PaginatorInterface $paginator, MusicBrainzAPIService $musicBrainzAPIService): Response
    {
        $query = $reviewRepository->getPaginationByAlbumQuery($album);

        $musicBrainzData = $musicBrainzAPIService->albumAction($album->getTitle(), $album->getArtist());

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'reviews' => $pagination,
            'musicBrainzData' => $musicBrainzData,
        ]);
    }

    //uses voter to only allow admins and the user who created the album to edit it
    #[Route('/album/{id}/edit', name: 'app_album_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_album', subject: 'album')]
    public function edit(Request $request, Album $album, EntityManagerInterface $entityManager, MusicBrainzAPIService $musicBrainzAPIService): Response
    {
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->get('autofill')->isClicked())
        {
            $artist = $album->getArtist();
            $title = $album->getTitle();
            $musicBrainzAutofillData = $musicBrainzAPIService->albumAutofillAction($title, $artist);

            if ($musicBrainzAutofillData) 
            {
                $album->setTrackList($musicBrainzAutofillData['tracks']);
                $album->setTitle($musicBrainzAutofillData['title']);
                $album->setArtist($musicBrainzAutofillData['artist']);;

                $this->addFlash('success', 'Album autofill data has been added.');
            }
            else
            {
                $this->addFlash('error', 'No album autofill data found.');
            }

            //recreate the form with the new data
            return $this->render('album/edit.html.twig', [
                'album' => $album,
                'form' => $this->createForm(AlbumType::class, $album)->createView(),
            ]);
        }

        //if successful, update the album and redirect to the album
        if ($form->isSubmitted() && $form->isValid())
        {
            $entityManager->flush();

            $this->addFlash('success', 'The album has been updated.');
            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/edit.html.twig', [
            'album' => $album,
            'form' => $form,
        ]);
    }

    //uses voter to only allow admins to delete albums
    #[Route('/album/{id}/delete', name: 'app_album_delete', methods: ['POST'])]
    #[IsGranted('delete_album', subject: 'album')]
    public function delete(Request $request, Album $album, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$album->getId(), $request->getPayload()->getString('_token')))
        {
            $entityManager->remove($album);
            $entityManager->flush();

            $this->addFlash('success', 'The album has been deleted.');
        }

        return $this->redirectToRoute('app_album_index', [], Response::HTTP_SEE_OTHER);
    }

}
