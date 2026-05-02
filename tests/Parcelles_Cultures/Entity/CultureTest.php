<?php

namespace App\Tests\Parcelles_Cultures\Entity;

use App\Entity\Parcelles_Cultures\Culture;
use PHPUnit\Framework\TestCase;

class CultureTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $culture = new Culture();
        $culture->setNomCulture('Tomate');
        $culture->setTypeCulture('Légume');
        $culture->setSaison('Été');
        $culture->setSurfaceUtilisee('15.5');
        $culture->setRendementEstime('50.0');
        
        $this->assertEquals('Tomate', $culture->getNomCulture());
        $this->assertEquals('Légume', $culture->getTypeCulture());
        $this->assertEquals('Été', $culture->getSaison());
        $this->assertEquals('15.5', $culture->getSurfaceUtilisee());
        $this->assertEquals('50.0', $culture->getRendementEstime());
        $this->assertEquals('active', $culture->getEtatCulture());
        $this->assertNotNull($culture->getCreatedAt());
    }

    public function testSurfaceUtiliseeAcceptsFloatAndStoresString(): void
    {
        $culture = new Culture();
        $culture->setSurfaceUtilisee(25.75); // Passed as float
        
        $this->assertSame('25.75', $culture->getSurfaceUtilisee()); // Stored and returned as string
    }
}
