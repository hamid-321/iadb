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

    public function findOneWithReviews(int $id): ?Album
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('r')
            ->leftJoin('r.reviewer', 'u')
            ->addSelect('u')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->orderBy('r.timestamp', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function searchWithReviews(?string $albumString, ?string $artistString, ?string $genreString, ?float $minRating, ?float $maxRating): array
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('r')
            ->leftJoin('r.reviewer', 'u')
            ->addSelect('u')
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

        return $queryBuilder
            ->orderBy('r.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
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
