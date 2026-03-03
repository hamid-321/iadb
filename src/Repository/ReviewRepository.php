<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function getPaginationByAlbumQuery(Album $album): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reviewer', 'u')
            ->addSelect('u')
            ->where('r.album = :album')
            ->setParameter('album', $album)
            ->orderBy('r.timestamp', 'DESC')
            ->getQuery();
    }

    public function getAPIPaginationQuery(?string $sortBy = 'timestamp', ?string $sortDirection = 'desc'): \Doctrine\ORM\Query
    {
        $queryBuilder = $this->createQueryBuilder('r')
        ->leftJoin('r.reviewer', 'u')
        ->addSelect('u');

        $validSortFields = ['timestamp', 'rating', 'id'];
        if (in_array($sortBy, $validSortFields, true)) {
            $sort = 'r.'.$sortBy;
        } 
        else {
            $sort = 'r.timestamp';
        }

        $direction = (strtoupper(($sortDirection)) === 'ASC') ? 'ASC' : 'DESC';

        $queryBuilder->orderBy($sort, $direction);

        return $queryBuilder->getQuery();
    }

    public function getAPIPaginationByAlbumQuery(Album $album, ?string $sortBy = 'timestamp', ?string $sortDirection = 'desc'): \Doctrine\ORM\Query
    {
        $queryBuilder = $this->createQueryBuilder('r')
        ->leftJoin('r.reviewer', 'u')
        ->addSelect('u')
        ->where('r.album = :album')
        ->setParameter('album', $album);

        $validSortFields = ['timestamp', 'rating', 'id'];
        if (in_array($sortBy, $validSortFields, true)) {
            $sort = 'r.'.$sortBy;
        } 
        else {
            $sort = 'r.timestamp';
        }

        $direction = (strtoupper(($sortDirection)) === 'ASC') ? 'ASC' : 'DESC';

        $queryBuilder->orderBy($sort, $direction);

        return $queryBuilder->getQuery();
    }

    public function getAPIPaginationByUserQuery(User $user, ?string $sortBy = 'timestamp', ?string $sortDirection = 'desc'): \Doctrine\ORM\Query
    {
        $queryBuilder = $this->createQueryBuilder('r')
        ->where('r.reviewer = :user')
        ->setParameter('user', $user);

        $validSortFields = ['timestamp', 'rating', 'id'];
        if (in_array($sortBy, $validSortFields, true)) {
            $sort = 'r.'.$sortBy;
        }
        else {
            $sort = 'r.timestamp';
        }

        $direction = (strtoupper(($sortDirection)) === 'ASC') ? 'ASC' : 'DESC';

        $queryBuilder->orderBy($sort, $direction);

        return $queryBuilder->getQuery();
    }

    public function getPaginationQuery(): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reviewer', 'u')
            ->addSelect('u')
            ->orderBy('r.timestamp', 'DESC')
            ->getQuery();
    }

    //    /**
    //     * @return Review[] Returns an array of Review objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Review
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
