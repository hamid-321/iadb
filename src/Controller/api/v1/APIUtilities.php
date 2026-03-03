<?php

namespace App\Controller\api\v1;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class APIUtilities
{

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function getPaginationMeta($pagination, Request $request, string $routeName, array $dataParams = []): array
    {
        $queryParams = $request->query->all();
        $currentPage = $pagination->getCurrentPageNumber();
        $totalPages = $pagination->getPageCount();
        $lastPage = $totalPages > 0 ? $totalPages : 1;

        return [
            'current_page'   => $currentPage,
            'total_items'    => $pagination->getTotalItemCount(),
            'items_per_page' => $pagination->getItemNumberPerPage(),
            'total_pages'    => $totalPages,
            'links' => [
                'self'  => $this->urlGenerator->generate($routeName, array_merge($queryParams, $dataParams, ['page' => $currentPage]), UrlGeneratorInterface::ABSOLUTE_URL),
                'first' => $this->urlGenerator->generate($routeName, array_merge($queryParams, $dataParams, ['page' => 1]), UrlGeneratorInterface::ABSOLUTE_URL),
                'last'  => $this->urlGenerator->generate($routeName, array_merge($queryParams, $dataParams, ['page' => $lastPage]), UrlGeneratorInterface::ABSOLUTE_URL),
                'next'  => ($currentPage < $totalPages) 
                            ? $this->urlGenerator->generate($routeName, array_merge($queryParams, $dataParams, ['page' => $currentPage + 1]), UrlGeneratorInterface::ABSOLUTE_URL) 
                            : null,
                'prev'  => ($currentPage > 1) 
                            ? $this->urlGenerator->generate($routeName, array_merge($queryParams, $dataParams, ['page' => $currentPage - 1]), UrlGeneratorInterface::ABSOLUTE_URL) 
                            : null,
            ],
        ];
    }
}