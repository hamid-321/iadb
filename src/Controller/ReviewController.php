<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\AverageRatingService;

final class ReviewController extends AbstractController
{
    //uses voter to only allow logged-in users to create reviews
    #[Route('/album/{id}/new', name: 'app_review_new', methods: ['GET', 'POST'])]
    #[IsGranted('create_review', subject: 'album')]
    public function new(Request $request, EntityManagerInterface $entityManager, Album $album, AverageRatingService $averageRatingService): Response
    {
        $user = $this->getUser();
        $review = new Review();

        $review->setAlbum($album);
        $review->setReviewer($user);
        $review->setTimestamp(new \DateTime());

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($review);

            $album->addReview($review); //so the average rating calc can pick the new review up
            $averageRatingService->calculateAverageRating($album);

            $entityManager->flush();

            $this->addFlash('success', 'Your review has been submitted!');
            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('review/new.html.twig', [
            'review' => $review,
            'album' => $album,
            'form' => $form,
        ]);
    }

    //uses voter to only allow the user who made the review, moderators and admins to edit the reviews
    #[Route('/review/{id}/edit', name: 'app_review_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_review', subject: 'review')]
    public function edit(Request $request, Review $review, EntityManagerInterface $entityManager, AverageRatingService $averageRatingService): Response
    {
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setTimestamp(new \DateTime());
            $entityManager->persist($review);

            $album = $review->getAlbum();
            $averageRatingService->calculateAverageRating($album); //uses service to calc album new avg rating

            $entityManager->flush();

            $this->addFlash('success', 'The review has been updated.');
            return $this->redirectToRoute('app_album_show', ['id' => $review->getAlbum()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('review/edit.html.twig', [
            'review' => $review,
            'album' => $review->getAlbum(),
            'form' => $form,
        ]);
    }

    //uses voter to only allow moderators, admins and the user who made the review to delete it
    #[Route('/review/{id}/delete', name: 'app_review_delete', methods: ['POST'])]
    #[IsGranted('delete_review', subject: 'review')]
    public function delete(Request $request, Review $review, EntityManagerInterface $entityManager, AlbumRepository $albumRepository, AverageRatingService $averageRatingService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$review->getId(), $request->getPayload()->getString('_token'))) {
            $album = $review->getAlbum();
            $albumId = $album->getId();

            $entityManager->remove($review);
            $entityManager->flush(); //flush early so the average rating calc can see that the review is deleted

            $album = $albumRepository->find($albumId);

            if ($album) {
                $averageRatingService->calculateAverageRating($album); //calc average rating then flush again to update
                $entityManager->flush();
            }
            $entityManager->flush(); //2nd flush so average rating for album is updated
            $this->addFlash('success', 'The review has been deleted.');
            return $this->redirectToRoute('app_album_show', ['id' => $albumId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_album_show', ['id' => $review->getAlbum()->getId()], Response::HTTP_SEE_OTHER);
    }
}
