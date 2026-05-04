<?php

namespace App\Tests\Evenement\Service;

use App\Entity\Evenement\Evenement;
use App\Service\Evenement\EvenementManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EvenementManagerTest extends TestCase
{
    public function testValidEvenement(): void
    {
        $manager = new EvenementManager();
        $evenement = $this->createValidEvenement();

        $this->assertTrue($manager->validate($evenement));
    }

    public function testEvenementWithoutTitre(): void
    {
        $manager = new EvenementManager();
        $evenement = $this->createValidEvenement()->setTitre('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $manager->validate($evenement);
    }

    public function testEvenementWithoutLieu(): void
    {
        $manager = new EvenementManager();
        $evenement = $this->createValidEvenement()->setLieu('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le lieu est obligatoire');

        $manager->validate($evenement);
    }

    public function testEvenementWithInvalidNombrePlaces(): void
    {
        $manager = new EvenementManager();
        $evenement = $this->createValidEvenement()->setNombrePlacesMax(0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de places doit être supérieur à zéro');

        $manager->validate($evenement);
    }

    public function testEvenementWithInvalidDates(): void
    {
        $manager = new EvenementManager();
        $evenement = $this->createValidEvenement()
            ->setDateDebut(new \DateTime('+1 day'))
            ->setDateFin(new \DateTime());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être postérieure à la date de début');

        $manager->validate($evenement);
    }

    private function createValidEvenement(): Evenement
    {
        return (new Evenement())
            ->setTitre('Symfony Live')
            ->setLieu('Tunis')
            ->setNombrePlacesMax(50)
            ->setDateDebut(new \DateTime())
            ->setDateFin(new \DateTime('+1 day'))
            ->setType('Conference');
    }
}
