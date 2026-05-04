<?php

namespace App\Tests\UserAndDiag\Service;

use App\Entity\UserAndDiag\User;
use App\Service\UserAndDiag\GamificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GamificationServiceTest extends TestCase
{
    private GamificationService $service;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->service = new GamificationService($this->em, $logger);
    }

    public function testGetUserStats(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setPassword('pass');
        $user->setPoints(750);
        $user->setLevel(2);

        $stats = $this->service->getUserStats($user);

        $this->assertSame(750, $stats['points']);
        $this->assertSame(2, $stats['level']);
        $this->assertArrayHasKey('id', $stats);
    }

    public function testAddPointsUpdatesLevel(): void
    {
        $user = new User();
        $user->setEmail('level@test.com');
        $user->setNom('Level');
        $user->setPrenom('Up');
        $user->setPassword('pass');
        $user->setPoints(0);
        $user->setLevel(1);

        // Mock: persist + flush
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');
        // Mock createQueryBuilder for checkPointBadges
        $this->em->method('createQueryBuilder')
            ->willThrowException(new \Exception('skip badge check in unit test'));

        $this->service->addPoints($user, 500);

        $this->assertSame(500, $user->getPoints());
        $this->assertSame(2, $user->getLevel()); // 1 + floor(500/500) = 2
    }

    public function testAddPointsLevelCalculation(): void
    {
        $user = new User();
        $user->setEmail('calc@test.com');
        $user->setNom('Calc');
        $user->setPrenom('Test');
        $user->setPassword('pass');
        $user->setPoints(400);
        $user->setLevel(1);

        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');
        $this->em->method('createQueryBuilder')
            ->willThrowException(new \Exception('skip'));

        $this->service->addPoints($user, 600);

        // 400 + 600 = 1000 → level = 1 + floor(1000/500) = 3
        $this->assertSame(1000, $user->getPoints());
        $this->assertSame(3, $user->getLevel());
    }
}
