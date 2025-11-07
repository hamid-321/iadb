<?php

namespace App\Controller;

use App\Entity\Album;
use App\Form\AlbumType;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AlbumController extends AbstractController
{
    //no restrictions, anyone can view the albums
    #[Route('/album', name: 'app_album_index', methods: ['GET'])]
    public function index(AlbumRepository $albumRepository): Response
    {
        return $this->render('album/index.html.twig', [
            'albums' => $albumRepository->findAll(),
        ]);
    }

    //uses voter to check that the user is allowed to make an album
    #[Route('/album/new', name: 'app_album_new', methods: ['GET', 'POST'])]
    #[IsGranted('create_album')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $album = new Album();
        $album->setAddedBy($user);

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);
        //if successful, create and redirect to the new album
        if ($form->isSubmitted() && $form->isValid()) {
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
    public function show(Album $album, AlbumRepository $albumRepository): Response
    {
        $album = $albumRepository->findOneWithReviews($album->getId());

        return $this->render('album/show.html.twig', [
            'album' => $album,
        ]);
    }

    //uses voter to only allow admins and the user who created the album to edit it
    #[Route('/album/{id}/edit', name: 'app_album_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_album', subject: 'album')]
    public function edit(Request $request, Album $album, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);
        //if successful, update the album and redirect to the album
        if ($form->isSubmitted() && $form->isValid()) {
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
        if ($this->isCsrfTokenValid('delete'.$album->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($album);
            $entityManager->flush();

            $this->addFlash('success', 'The album has been deleted.');
        }

        return $this->redirectToRoute('app_album_index', [], Response::HTTP_SEE_OTHER);
    }
}
