<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Wishlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wishlist>
 *
 * @method Wishlist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wishlist|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wishlist[]    findAll()
 * @method Wishlist[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    public function save(Wishlist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Wishlist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Wishlist[] Returns an array of Wishlist objects
//     */
//    public function findByUser($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.user = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.dateAjout', 'DESC')
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    public function findAllIdsByUser($user): array
    {
        if (!$user) return [];
        
        $results = $this->createQueryBuilder('w')
            ->select('p.id') // Dans l'entité Produits, le champ s'appelle $id (colonne idProduit)
            ->join('w.produit', 'p')
            ->where('w.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'id');
    }
}
