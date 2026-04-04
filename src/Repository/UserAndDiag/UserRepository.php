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

    public function persistResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        /** @var CustomResetPasswordRequest $resetPasswordRequest */
        /** @var User $user */
        $user = $resetPasswordRequest->getUser();
        $payload = json_encode([
            'selector' => $resetPasswordRequest->getSelector(),
            'hashedToken' => $resetPasswordRequest->getHashedToken(),
            'expiresAt' => $resetPasswordRequest->getExpiresAt()->getTimestamp(),
            'requestedAt' => $resetPasswordRequest->getRequestedAt()->getTimestamp(),
        ]);

        $user->setResetPasswordCode($payload);
        $user->setResetPasswordExpiresAt($resetPasswordRequest->getExpiresAt());
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findResetPasswordRequest(string $selector): ?ResetPasswordRequestInterface
    {
        // Safe LIKE query for selector string in JSON payload
        $users = $this->createQueryBuilder('u')
            ->where('u.reset_password_code LIKE :selector')
            ->setParameter('selector', '%"selector":"' . $selector . '"%')
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            $data = json_decode($user->getResetPasswordCode() ?? '', true);
            if (is_array($data) && isset($data['selector']) && $data['selector'] === $selector) {
                $expiresAt = (new \DateTimeImmutable())->setTimestamp($data['expiresAt']);
                $requestedAt = (new \DateTimeImmutable())->setTimestamp($data['requestedAt']);

                return new CustomResetPasswordRequest(
                    $user,
                    $expiresAt,
                    $data['selector'],
                    $data['hashedToken'],
                    $requestedAt
                );
            }
        }

        return null;
    }

    public function getMostRecentNonExpiredRequestDate(object $user): ?\DateTimeInterface
    {
        /** @var User $user */
        $code = $user->getResetPasswordCode();
        if (!$code) {
            return null;
        }

        $data = json_decode($code, true);
        if (!$data || !isset($data['expiresAt'], $data['requestedAt'])) {
            return null;
        }

        if ($data['expiresAt'] <= time()) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($data['requestedAt']);
    }

    public function removeResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        /** @var User $user */
        $user = $resetPasswordRequest->getUser();
        $user->setResetPasswordCode(null);
        $user->setResetPasswordExpiresAt(null);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
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
}
