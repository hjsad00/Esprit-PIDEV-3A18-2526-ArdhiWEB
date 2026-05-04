<?php

namespace App\Tests\UserAndDiag\Service;

use App\Entity\UserAndDiag\User;
use App\Entity\UserAndDiag\Abonnement;
use App\Entity\UserAndDiag\Offre;
use App\Repository\UserAndDiag\AbonnementRepository;
use App\Service\UserAndDiag\SubscriptionFeatureService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriptionFeatureServiceTest extends TestCase
{
    private SubscriptionFeatureService $service;
    private AbonnementRepository&MockObject $aboRepo;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->aboRepo = $this->createMock(AbonnementRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new SubscriptionFeatureService($this->aboRepo, $this->em);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('sub@test.com');
        $user->setNom('Sub');
        $user->setPrenom('Test');
        $user->setPassword('pass');
        return $user;
    }

    public function testGetFeaturesReturnsFreeLimitsWhenNoSubscription(): void
    {
        $user = $this->createUser();
        $this->aboRepo->method('findActiveByUser')->willReturn(null);

        $features = $this->service->getFeatures($user);

        $this->assertSame(2, $features['diagnosticsParHeure']);
        $this->assertFalse($features['accesTraitement']);
        $this->assertFalse($features['accesPlanTraitement']);
    }

    public function testGetFeaturesReturnsOffreLimits(): void
    {
        $user = $this->createUser();

        $offre = new Offre();
        $offre->setNom('Premium');
        $offre->setPrixMensuel(29.99);
        $offre->setDiagnosticsParHeure(-1);
        $offre->setAccesTraitement(true);
        $offre->setAccesPlanTraitement(true);

        $abo = new Abonnement();
        $abo->setUser($user);
        $abo->setOffre($offre);
        $abo->setPrix(29.99);

        $this->aboRepo->method('findActiveByUser')->willReturn($abo);

        $features = $this->service->getFeatures($user);

        $this->assertSame(-1, $features['diagnosticsParHeure']);
        $this->assertTrue($features['accesTraitement']);
        $this->assertTrue($features['accesPlanTraitement']);
    }

    public function testCanPerformDiagnosticUnlimited(): void
    {
        $user = $this->createUser();

        $offre = new Offre();
        $offre->setNom('Premium');
        $offre->setPrixMensuel(29.99);
        $offre->setDiagnosticsParHeure(-1);
        $offre->setAccesTraitement(true);
        $offre->setAccesPlanTraitement(true);

        $abo = new Abonnement();
        $abo->setUser($user);
        $abo->setOffre($offre);
        $abo->setPrix(29.99);

        $this->aboRepo->method('findActiveByUser')->willReturn($abo);

        // -1 = unlimited → always true
        $this->assertTrue($this->service->canPerformDiagnostic($user));
    }
}
