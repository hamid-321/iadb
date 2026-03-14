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
use App\Service\YoutubeAPIService;
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

        //get the session and the current autofill choices
        $session = $request->getSession();
        $mbidChoices = $session->get('album_autofill_choices', []);

        //create form with autofill choices
        //will display the autofill selector if mbid_choices is not empty
        $form = $this->createForm(AlbumType::class, $album, ['mbid_choices' => $mbidChoices]);
        $form->handleRequest($request);

        if ($form->get('autofill')->isClicked())
        {
            //find the results from the query from the mb api
            $searchResults = $musicBrainzAPIService->albumSearchAction($album->getTitle(), $album->getArtist());

            if ($searchResults !== null && $searchResults !== [])
            {
                //if we have results, add them to the choices array,
                //format then as title, artist and date so they can be distinguished easily
                $mbidChoices = [];
                foreach ($searchResults as $autofillChoice)
                {
                    $label = $autofillChoice['title'] . ' – ' . $autofillChoice['artist'];
                    if ($autofillChoice['date'] !== null && $autofillChoice['date'] !== '')
                    {
                        $label .= ' (' . $autofillChoice['date'] . ')';
                    }
                    $mbidChoices[$label] = $autofillChoice['mbid'];
                }
                //save the choices to the session
                $session->set('album_autofill_choices', $mbidChoices);
                $this->addFlash('success', count($mbidChoices) . ' album(s) found. Select one and click "Use selected album".');
            }
            else
            {
                //remove all choices and empty the array if no results and error message
                $session->remove('album_autofill_choices');
                $mbidChoices = [];
                $this->addFlash('error', 'No albums found. Check the title and artist.');
            }

            $form = $this->createForm(AlbumType::class, $album, ['mbid_choices' => $mbidChoices]);

            return $this->render('album/new.html.twig', [
                'album' => $album,
                'form' => $form->createView(),
                'musicBrainzSearchResults' => $searchResults ?? [],
            ]);
        }

        if ($form->get('useSelected')->isClicked())
        {
            //get the chosen mbid, remove autofill choices
            $selectedMbid = $form->get('selectedMbid')->getData();
            $session->remove('album_autofill_choices');

            if ($selectedMbid !== null && $selectedMbid !== '')
            {
                //get the autofill data from the mb api
                $autofillData = $musicBrainzAPIService->albumAutofillByMbid($selectedMbid);
                //if we have data, set the album data
                if ($autofillData !== null)
                {
                    $album->setTrackList($autofillData['tracks']);
                    $album->setTitle($autofillData['title']);
                    $album->setArtist($autofillData['artist']);
                    if (isset($autofillData['genre']) && $autofillData['genre'] !== '')
                    {
                        $album->setGenre($autofillData['genre']);
                    }
                    $this->addFlash('success', 'Album data has been filled. You can edit or submit.');
                }
                else
                {
                    $this->addFlash('error', 'Could not load album data.');
                }
            }
            else
            {
                $this->addFlash('error', 'Please select an album from the list.');
            }

            return $this->render('album/new.html.twig', [
                'album' => $album,
                'form' => $this->createForm(AlbumType::class, $album, ['mbid_choices' => []])->createView(),
                'musicBrainzSearchResults' => [],
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
            'form' => $form->createView(),
            'musicBrainzSearchResults' => [],
        ]);
    }

    //once again, no restrictions, anyone can view an album
    #[Route('/album/{id}', name: 'app_album_show', methods: ['GET'])]
    public function show(Request $request, Album $album, ReviewRepository $reviewRepository, PaginatorInterface $paginator, MusicBrainzAPIService $musicBrainzAPIService, YoutubeAPIService $youtubeAPIService): Response
    {
        $query = $reviewRepository->getPaginationByAlbumQuery($album);

        $musicBrainzData = $musicBrainzAPIService->albumAction($album->getTitle(), $album->getArtist());

        $youtubeVideoId = null;
        //only search for a video if we have musicbrainz data, this means the album is real and not any words
        //this prevents dodgy videos from being searched for and displayed
        if ($musicBrainzData)
        {
            //convert track list from string to array before passing to youtube api service
            $trackTitles = [];
            if ($album->getTrackList()) 
            {
                $trackTitles = array_values(array_filter(
                    array_map('trim', preg_split('/\r\n|\n/', $album->getTrackList(), -1, PREG_SPLIT_NO_EMPTY))
                ));
            }

            $youtubeVideoId = $youtubeAPIService->searchVideos($album->getTitle(), $album->getArtist(), $trackTitles);
        }

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'reviews' => $pagination,
            'musicBrainzData' => $musicBrainzData,
            'youtubeVideoId' => $youtubeVideoId,
        ]);
    }

    //uses voter to only allow admins and the user who created the album to edit it
    #[Route('/album/{id}/edit', name: 'app_album_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_album', subject: 'album')]
    public function edit(Request $request, Album $album, EntityManagerInterface $entityManager, MusicBrainzAPIService $musicBrainzAPIService): Response
    {
        $session = $request->getSession();
        $mbidChoices = $session->get('album_autofill_choices', []);

        $form = $this->createForm(AlbumType::class, $album, ['mbid_choices' => $mbidChoices]);
        $form->handleRequest($request);

        if ($form->get('autofill')->isClicked())
        {
            $searchResults = $musicBrainzAPIService->albumSearchAction($album->getTitle(), $album->getArtist());

            if ($searchResults !== null && $searchResults !== [])
            {
                $mbidChoices = [];
                foreach ($searchResults as $a)
                {
                    $label = $a['title'] . ' – ' . $a['artist'];
                    if ($a['date'] !== null && $a['date'] !== '')
                    {
                        $label .= ' (' . $a['date'] . ')';
                    }
                    $mbidChoices[$label] = $a['mbid'];
                }
                $session->set('album_autofill_choices', $mbidChoices);
                $this->addFlash('success', count($mbidChoices) . ' album(s) found. Select one and click "Use selected album".');
            }
            else
            {
                $session->remove('album_autofill_choices');
                $mbidChoices = [];
                $this->addFlash('error', 'No albums found. Check the title and artist.');
            }

            $form = $this->createForm(AlbumType::class, $album, ['mbid_choices' => $mbidChoices]);

            return $this->render('album/edit.html.twig', [
                'album' => $album,
                'form' => $form->createView(),
                'musicBrainzSearchResults' => $searchResults ?? [],
            ]);
        }

        if ($form->get('useSelected')->isClicked())
        {
            $selectedMbid = $form->get('selectedMbid')->getData();
            $session->remove('album_autofill_choices');
            if ($selectedMbid !== null && $selectedMbid !== '')
            {
                $autofillData = $musicBrainzAPIService->albumAutofillByMbid($selectedMbid);
                if ($autofillData !== null)
                {
                    $album->setTrackList($autofillData['tracks']);
                    $album->setTitle($autofillData['title']);
                    $album->setArtist($autofillData['artist']);
                    if (isset($autofillData['genre']) && $autofillData['genre'] !== '')
                    {
                        $album->setGenre($autofillData['genre']);
                    }
                    $this->addFlash('success', 'Album data has been filled. You can edit and save.');
                }
                else
                {
                    $this->addFlash('error', 'Could not load album data.');
                }
            }
            else
            {
                $this->addFlash('error', 'Please select an album from the list.');
            }

            return $this->render('album/edit.html.twig', [
                'album' => $album,
                'form' => $this->createForm(AlbumType::class, $album, ['mbid_choices' => []])->createView(),
                'musicBrainzSearchResults' => [],
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
            'form' => $form->createView(),
            'musicBrainzSearchResults' => [],
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
