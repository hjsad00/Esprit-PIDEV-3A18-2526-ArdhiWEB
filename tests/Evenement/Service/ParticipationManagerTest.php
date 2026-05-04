<?php

namespace App\Tests\Evenement\Service;

use App\Entity\Evenement\Participation;
use App\Service\Evenement\ParticipationManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParticipationManagerTest extends TestCase
{
    public function testValidParticipation(): void
    {
        $manager = new ParticipationManager();
        $participation = $this->createValidParticipation();

        $this->assertTrue($manager->validate($participation));
    }

    public function testParticipationWithInvalidNombrePersonnes(): void
    {
        $manager = new ParticipationManager();
        $participation = $this->createValidParticipation()->setNombrePersonnes(0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de personnes doit être entre 1 et 10');

        $manager->validate($participation);
    }

    public function testParticipationWithInvalidNote(): void
    {
        $manager = new ParticipationManager();
        $participation = $this->createValidParticipation()->setNote(6);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être entre 0 et 5');

        $manager->validate($participation);
    }

    public function testParticipationWithInvalidStatut(): void
    {
        $manager = new ParticipationManager();
        $participation = $this->createValidParticipation()->setStatut('INCONNU');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut est invalide');

        $manager->validate($participation);
    }

    private function createValidParticipation(): Participation
    {
        return (new Participation())
            ->setNombrePersonnes(2)
            ->setNote(3)
            ->setStatut('CONFIRME');
    }
}
