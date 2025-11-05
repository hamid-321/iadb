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
    #[Route('/album', name: 'app_album_index', methods: ['GET'])]
    public function index(AlbumRepository $albumRepository): Response
    {
        return $this->render('album/index.html.twig', [
            'albums' => $albumRepository->findAll(),
        ]);
    }

    #[Route('/album/new', name: 'app_album_new', methods: ['GET', 'POST'])]
    #[IsGranted('create_album')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $album = new Album();
        $album->setAddedBy($user);

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($album);
            $entityManager->flush();

            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/new.html.twig', [
            'album' => $album,
            'form' => $form,
        ]);
    }

    #[Route('/album/{id}', name: 'app_album_show', methods: ['GET'])]
    public function show(Album $album, AlbumRepository $albumRepository): Response
    {
        $album = $albumRepository->findOneWithReviews($album->getId());

        return $this->render('album/show.html.twig', [
            'album' => $album,
        ]);
    }

    #[Route('/album/{id}/edit', name: 'app_album_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_album', subject: 'album')]
    public function edit(Request $request, Album $album, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/edit.html.twig', [
            'album' => $album,
            'form' => $form,
        ]);
    }

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
