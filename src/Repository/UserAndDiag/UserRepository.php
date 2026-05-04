<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;
use App\Security\CustomResetPasswordRequest;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, ResetPasswordRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find users by role, optionally filtering by search string.
     * 
     * @param string $role
     * @param string|null $search
     * @return User[]
     */
    public function findByRole(string $role, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $role);

        if ($search) {
            $qb->andWhere('u.email LIKE :search OR u.nom LIKE :search OR u.prenom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
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

    public function createResetPasswordRequest(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface
    {
        return new CustomResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }

    public function getUserIdentifier(object $user): string
    {
        return $user->getUserIdentifier();
    }


    public function generateAndSaveNativeToken(object $user): string
    {
        // Generate random 4 digit code to match the user's requested workflow
        $code = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        /** @var User $user */
        $user->setResetPasswordCode($code);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $code;
    }

    public function findUserByResetCode(string $code): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.reset_password_code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function removeExpiredResetPasswordRequests(): int
    {
        $users = $this->createQueryBuilder('u')
            ->where('u.reset_password_code IS NOT NULL')
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($users as $user) {
            $data = json_decode($user->getResetPasswordCode() ?? '', true);
            if (is_array($data) && isset($data['expiresAt']) && $data['expiresAt'] <= time()) {
                $user->setResetPasswordCode(null);
                $user->setResetPasswordExpiresAt(null);
                $this->getEntityManager()->persist($user);
                $count++;
            }
        }

        if ($count > 0) {
            $this->getEntityManager()->flush();
        }

        return $count;
    }

    public function persistResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
    }

    public function findResetPasswordRequest(string $hashedToken): ?ResetPasswordRequestInterface
    {
        return null;
    }

    public function getMostRecentNonExpiredRequestDate(object $user): ?\DateTimeInterface
    {
        return null;
    }

    public function removeResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
    }
}
