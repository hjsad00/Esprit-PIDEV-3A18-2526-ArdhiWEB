<?php

namespace App\Tests\Parcelles_Cultures\Entity;

use App\Entity\Parcelles_Cultures\IrrigationRequest;
use PHPUnit\Framework\TestCase;

class IrrigationRequestTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $irrigation = new IrrigationRequest();
        $irrigation->setTemperatureMoyenne('25.5');
        $irrigation->setTemperatureMax('30.0');
        $irrigation->setTemperatureMin('15.0');
        $irrigation->setPrecipitations('0.0');
        $irrigation->setHumidite('40.5');
        $irrigation->setKc('1.2');
        
        $this->assertEquals('25.5', $irrigation->getTemperatureMoyenne());
        $this->assertEquals('30.0', $irrigation->getTemperatureMax());
        $this->assertEquals('15.0', $irrigation->getTemperatureMin());
        $this->assertEquals('0.0', $irrigation->getPrecipitations());
        $this->assertEquals('40.5', $irrigation->getHumidite());
        $this->assertEquals('1.2', $irrigation->getKc());
        $this->assertNotNull($irrigation->getCreatedAt());
    }
}
