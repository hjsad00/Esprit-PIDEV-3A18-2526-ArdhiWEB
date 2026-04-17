<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\UserBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBlock>
 *
 * @method UserBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserBlock[]    findAll()
 * @method UserBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBlock::class);
    }

    public function save(UserBlock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserBlock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
