<?php

namespace App\Service\UserAndDiag;

use App\Entity\UserAndDiag\User;
use App\Repository\UserAndDiag\AbonnementRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionFeatureService
{
    private AbonnementRepository $aboRepo;
    private EntityManagerInterface $em;

    public function __construct(AbonnementRepository $aboRepo, EntityManagerInterface $em)
    {
        $this->aboRepo = $aboRepo;
        $this->em = $em;
    }

    /**
     * Get the active limits for a user (either from their active Abonnement or FREE defaults)
     */
    public function getFeatures(User $user): array
    {
        $abo = $this->aboRepo->findActiveByUser($user);

        if ($abo && $abo->getOffre()) {
            $offre = $abo->getOffre();
            return [
                'diagnosticsParHeure' => $offre->getDiagnosticsParHeure(),
                'accesTraitement' => $offre->isAccesTraitement(),
                'accesPlanTraitement' => $offre->isAccesPlanTraitement(),
            ];
        }

        // Default FREE plan limits
        return [
            'diagnosticsParHeure' => 2, // Free limit: 2 per hour
            'accesTraitement' => false, // Free plan doesn't show advanced treatments
            'accesPlanTraitement' => false, // Free plan can't access full treatment plans
        ];
    }

    public function getDiagnosticUsageCount(User $user): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(d.id)')
            ->from('App\Entity\UserAndDiag\Diagnostic', 'd')
            ->where('d.user = :user')
            ->andWhere('d.date_scan > :oneHourAgo')
            ->setParameter('user', $user)
            ->setParameter('oneHourAgo', new \DateTime('-1 hour'));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Check if the user is allowed to perform a diagnostic right now.
     */
    public function canPerformDiagnostic(User $user): bool
    {
        $limit = $this->getFeatures($user)['diagnosticsParHeure'];

        if ($limit === -1) {
            return true;
        }

        $count = $this->getDiagnosticUsageCount($user);

        return $count < $limit;
    }
}
