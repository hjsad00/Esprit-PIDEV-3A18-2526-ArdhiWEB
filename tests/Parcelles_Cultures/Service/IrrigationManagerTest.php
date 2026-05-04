<?php

namespace App\Tests\Parcelles_Cultures\Service;

use App\Entity\Parcelles_Cultures\IrrigationRequest;
use App\Service\Parcelles_Cultures\IrrigationManager;
use PHPUnit\Framework\TestCase;

class IrrigationManagerTest extends TestCase
{
    public function testValidIrrigation()
    {
        $irrigation = new IrrigationRequest();
        $irrigation->setVolumeLitres('1000');
        $irrigation->setHumidite('65');

        $manager = new IrrigationManager();
        $this->assertTrue($manager->validate($irrigation));
    }

    public function testIrrigationWithNegativeQuantity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le volume d\'eau doit être positif');

        $irrigation = new IrrigationRequest();
        $irrigation->setVolumeLitres('-500');
        $irrigation->setHumidite('65');

        $manager = new IrrigationManager();
        $manager->validate($irrigation);
    }

    public function testIrrigationWithInvalidHumidity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'humidité doit être entre 0 et 100%');

        $irrigation = new IrrigationRequest();
        $irrigation->setVolumeLitres('1000');
        $irrigation->setHumidite('150');

        $manager = new IrrigationManager();
        $manager->validate($irrigation);
    }
}
