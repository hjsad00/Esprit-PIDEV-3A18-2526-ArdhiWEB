<?php

namespace App\Tests\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Service\MaterielEtMaintenance\MaterielManager;
use PHPUnit\Framework\TestCase;

class MaterielManagerTest extends TestCase
{
    public function testValidMateriel()
    {
        $materiel = new Materiel();
        $materiel->setNom('Tracteur John Deere');
        $materiel->setHeuresUtilisation(150);

        $manager = new MaterielManager();

        $this->assertTrue($manager->validate($materiel));
    }

    public function testMaterielWithoutName()
    {
        $this->expectException(\InvalidArgumentException::class);

        $materiel = new Materiel();
        // Pas de nom défini (donc null/vide)
        $materiel->setHeuresUtilisation(150);

        $manager = new MaterielManager();
        $manager->validate($materiel);
    }

    public function testMaterielWithNegativeHours()
    {
        $this->expectException(\InvalidArgumentException::class);

        $materiel = new Materiel();
        $materiel->setNom('Moissonneuse');
        $materiel->setHeuresUtilisation(-10);

        $manager = new MaterielManager();
        $manager->validate($materiel);
    }
}
