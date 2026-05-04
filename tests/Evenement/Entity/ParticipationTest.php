<?php

namespace App\Tests\Evenement\Entity;

use App\Entity\Evenement\Participation;
use PHPUnit\Framework\TestCase;

class ParticipationTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $participation = new Participation();
        $participation
            ->setStatut('CONFIRME')
            ->setNombrePersonnes(3)
            ->setNote(4)
            ->setAvis('Très bien');

        $this->assertSame('CONFIRME', $participation->getStatut());
        $this->assertSame(3, $participation->getNombrePersonnes());
        $this->assertSame(4, $participation->getNote());
        $this->assertSame('Très bien', $participation->getAvis());
    }

    public function testDefaultValues(): void
    {
        $participation = new Participation();

        $this->assertSame('CONFIRME', $participation->getStatut());
        $this->assertSame(1, $participation->getNombrePersonnes());
        $this->assertSame(0, $participation->getNote());
        $this->assertFalse($participation->isAttestationEnvoyee());
        $this->assertFalse($participation->isRappelJ3Envoye());
        $this->assertFalse($participation->isRappelJ1Envoye());
    }

    public function testQrCodeTokenGeneratedOnConstruct(): void
    {
        $participation = new Participation();

        $this->assertNotNull($participation->getQrCodeToken());
        $this->assertSame(32, strlen((string) $participation->getQrCodeToken()));
    }

    public function testDateInscriptionSetOnConstruct(): void
    {
        $participation = new Participation();

        $this->assertNotNull($participation->getDateInscription());
    }
}
