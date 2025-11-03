<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\AlbumRepository;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(AlbumRepository $albumRepository): Response
    {
        $allAlbums = $albumRepository->findBy(
            [], //without using WHERE
            ['id' => 'DESC'] //ordering by ID 
        );
        return $this->render('home/index.html.twig', [
            'User_email' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null,
            'AllAlbums' => $allAlbums,
        ]);
    }
}
