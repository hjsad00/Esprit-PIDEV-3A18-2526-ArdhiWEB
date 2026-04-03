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
}
