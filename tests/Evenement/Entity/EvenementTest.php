<?php

namespace App\Tests\Evenement\Entity;

use App\Entity\Evenement\Evenement;
use PHPUnit\Framework\TestCase;

class EvenementTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $evenement = new Evenement();
        $evenement
            ->setTitre('Forum Symfony')
            ->setLieu('Sousse')
            ->setType('Conference')
            ->setNombrePlacesMax(100)
            ->setStatut('TERMINE');

        $this->assertSame('Forum Symfony', $evenement->getTitre());
        $this->assertSame('Sousse', $evenement->getLieu());
        $this->assertSame('Conference', $evenement->getType());
        $this->assertSame(100, $evenement->getNombrePlacesMax());
        $this->assertSame('TERMINE', $evenement->getStatut());
        $this->assertNotNull($evenement->getDateCreation());
    }

    public function testDefaultStatut(): void
    {
        $evenement = new Evenement();

        $this->assertSame('A_VENIR', $evenement->getStatut());
    }

    public function testNombrePlacesMax(): void
    {
        $evenement = new Evenement();
        $evenement->setNombrePlacesMax(50);

        $this->assertSame(50, $evenement->getNombrePlacesMax());
    }
}
