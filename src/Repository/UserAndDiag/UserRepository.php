<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    public function findByRole(string $role, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $role)
            ->orderBy('u.nom', 'ASC');
 
        if ($search !== '') {
            $qb->andWhere('u.nom LIKE :s OR u.prenom LIKE :s OR u.email LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }
 
        return $qb->getQuery()->getResult();
    }
}
