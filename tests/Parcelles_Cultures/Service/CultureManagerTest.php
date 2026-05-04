<?php

namespace App\Tests\Parcelles_Cultures\Service;

use App\Entity\Parcelles_Cultures\Culture;
use App\Service\Parcelles_Cultures\CultureManager;
use PHPUnit\Framework\TestCase;

class CultureManagerTest extends TestCase
{
    public function testValidCulture()
    {
        $culture = new Culture();
        $culture->setSurfaceUtilisee('50.5');
        $culture->setDatePlantation(new \DateTime('2026-05-01'));
        $culture->setDateRecoltePrevue(new \DateTime('2026-08-01'));

        $manager = new CultureManager();
        $this->assertTrue($manager->validate($culture));
    }

    public function testCultureWithNegativeSurface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La surface doit être positive');

        $culture = new Culture();
        $culture->setSurfaceUtilisee('-10');
        $culture->setDatePlantation(new \DateTime('2026-05-01'));
        $culture->setDateRecoltePrevue(new \DateTime('2026-08-01'));

        $manager = new CultureManager();
        $manager->validate($culture);
    }

    public function testCultureWithInvalidDates()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de récolte doit être postérieure à la date de plantation');

        $culture = new Culture();
        $culture->setSurfaceUtilisee('50');
        $culture->setDatePlantation(new \DateTime('2026-08-01'));
        $culture->setDateRecoltePrevue(new \DateTime('2026-05-01'));

        $manager = new CultureManager();
        $manager->validate($culture);
    }
}
