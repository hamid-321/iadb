<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\PasswordHasherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;

final class UserController extends AbstractController
{
    //allows a user to view their own profile, hence app_user_profile
    #[Route('/user/profile/{id}', name: 'app_user_profile', methods: ['GET'])]
    #[IsGranted('view_user', subject: 'user')]
    public function profile(User $user): Response
    {
        return $this->render('user/profile.html.twig', [
            'user' => $user,
        ]);
    }

    //allows a user to edit their own profile ONLY
    #[Route('/user/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_user', subject: 'user')]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, PasswordHasherService $passwordHasher, Security $security): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $passwordHasher->hashPasswordForUser($user, $plainPassword);

            $entityManager->flush();
            // //serialisation issue, remove the file so it is not attempted to be serialised
            // file has already been uploaded, fine to do this...
            $user->setProfilePictureFile(null);

            //solve issue when admin updates their own roles
            $currentUser = $this->getUser();
            if ($currentUser->getId() === $user->getId()) {
                $entityManager->refresh($user); //refresh to get new roles
                $security->login($user);// log back in
            }
            $this->addFlash('success', 'The user has been updated.');
            if ($this->isGranted('user_admin', subject: $currentUser)) {
                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }
            else {
                return $this->redirectToRoute('app_user_profile', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    //admin routes only, uses voters again
    #[Route('/user/admin', name: 'app_user_index', methods: ['GET'])]
    #[IsGranted('user_admin')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    //once again admin route
    #[Route('/user/admin/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    #[IsGranted('create_user')]
    public function new(Request $request, EntityManagerInterface $entityManager, PasswordHasherService $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $passwordHasher->hashPasswordForUser($user, $plainPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'The user has been created.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    //another admin only function, different to app_user_profile route
    #[Route('/user/admin/{id}', name: 'app_user_show', methods: ['GET'])]
    #[IsGranted('user_admin', subject: 'user')]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    //once again.. only allow admins to delete users via voters
    #[Route('/user/admin/{id}', name: 'app_user_delete', methods: ['POST'])]
    #[IsGranted('delete_user', subject: 'user')]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }
        $this->addFlash('success', 'The user has been deleted.');
        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
