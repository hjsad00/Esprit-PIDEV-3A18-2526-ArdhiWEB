<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\Diagnostic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Diagnostic>
 *
 * @method Diagnostic|null find($id, $lockMode = null, $lockVersion = null)
 * @method Diagnostic|null findOneBy(array $criteria, array $orderBy = null)
 * @method Diagnostic[]    findAll()
 * @method Diagnostic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DiagnosticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Diagnostic::class);
    }

    /**
     * @return Diagnostic[]
     */
    public function findByUserAndKeyword(int $userId, ?string $keyword = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('d.date_scan', 'DESC');

        if ($keyword) {
            $qb->andWhere('d.resultat_ia LIKE :keyword')
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Diagnostic[]
     */
    public function findWithLocation(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.latitude IS NOT NULL')
            ->andWhere('d.longitude IS NOT NULL')
            ->orderBy('d.date_scan', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
