<?php

namespace App\Repository;

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function getPaginationQuery(): \Doctrine\ORM\Query
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('r')
            ->leftJoin('r.reviewer', 'u')
            ->addSelect('u')
            ->addSelect('LOWER(a.title) AS HIDDEN lower_title')
            ->distinct();

        return $queryBuilder->getQuery();
    }

    public function getPaginationSearchQuery(?string $albumString, ?string $artistString, ?string $genreString, ?float $minRating, ?float $maxRating): \Doctrine\ORM\Query
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('r')
            ->leftJoin('r.reviewer', 'u')
            ->addSelect('u')
            ->addSelect('LOWER(a.title) AS HIDDEN lower_title')
            ->distinct(); //stop duplication

        if ($albumString !== null && $albumString !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->like('LOWER(a.title)', ':albumString'))
            ->setParameter('albumString', '%'.mb_strtolower($albumString).'%');
        }

        if ($artistString !== null && $artistString !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->like('LOWER(a.artist)', ':artistString'))
            ->setParameter('artistString', '%'.mb_strtolower($artistString).'%');
        }

        if ($genreString !== null && $genreString !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->like('LOWER(a.genre)', ':genreString'))
            ->setParameter('genreString', '%'.mb_strtolower($genreString).'%');
        }

        if ($minRating !== null) {
            $queryBuilder->andWhere('a.averageRating IS NOT NULL')
            ->andWhere('a.averageRating >= :minRating')
            ->setParameter('minRating', $minRating);
        }

        if ($maxRating !== null) {
            $queryBuilder->andWhere('a.averageRating IS NOT NULL')
            ->andWhere('a.averageRating <= :maxRating')
            ->setParameter('maxRating', $maxRating);
        }

        return $queryBuilder->getQuery();
    }



    //    /**
    //     * @return Album[] Returns an array of Album objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Album
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
