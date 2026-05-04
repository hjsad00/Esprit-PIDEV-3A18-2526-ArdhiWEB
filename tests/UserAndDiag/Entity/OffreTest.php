<?php

namespace App\Tests\UserAndDiag\Entity;

use App\Entity\UserAndDiag\Offre;
use PHPUnit\Framework\TestCase;

class OffreTest extends TestCase
{
    private Offre $offre;

    protected function setUp(): void
    {
        $this->offre = new Offre();
    }

    public function testConstructorSetsDateCreation(): void
    {
        $this->assertNotNull($this->offre->getDateCreation());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->offre->getDateCreation());
    }

    public function testDefaultValues(): void
    {
        $this->assertSame('#6B7F3F', $this->offre->getCouleurPrimaire());
        $this->assertSame('#4A5A2B', $this->offre->getCouleurSecondaire());
        $this->assertTrue($this->offre->isEstActive());
        $this->assertFalse($this->offre->isEstRecommandee());
        $this->assertSame(3, $this->offre->getDiagnosticsParHeure());
        $this->assertFalse($this->offre->isAccesTraitement());
        $this->assertFalse($this->offre->isAccesPlanTraitement());
    }

    public function testGetSetNom(): void
    {
        $this->offre->setNom('Premium');
        $this->assertSame('Premium', $this->offre->getNom());
    }

    public function testGetSetPrixMensuel(): void
    {
        $this->offre->setPrixMensuel(29.99);
        $this->assertSame(29.99, $this->offre->getPrixMensuel());
    }

    public function testGetSetDescription(): void
    {
        $this->offre->setDescription('Accès illimité');
        $this->assertSame('Accès illimité', $this->offre->getDescription());
    }

    public function testGetSetAvantages(): void
    {
        $this->offre->setAvantages('Diagnostic illimité, Plan de traitement');
        $this->assertSame('Diagnostic illimité, Plan de traitement', $this->offre->getAvantages());
    }

    public function testPremiumOffreConfig(): void
    {
        $this->offre->setDiagnosticsParHeure(-1); // illimité
        $this->offre->setAccesTraitement(true);
        $this->offre->setAccesPlanTraitement(true);

        $this->assertSame(-1, $this->offre->getDiagnosticsParHeure());
        $this->assertTrue($this->offre->isAccesTraitement());
        $this->assertTrue($this->offre->isAccesPlanTraitement());
    }

    public function testGetSetCouleurs(): void
    {
        $this->offre->setCouleurPrimaire('#FF0000');
        $this->offre->setCouleurSecondaire('#00FF00');

        $this->assertSame('#FF0000', $this->offre->getCouleurPrimaire());
        $this->assertSame('#00FF00', $this->offre->getCouleurSecondaire());
    }

    public function testEstRecommandee(): void
    {
        $this->offre->setEstRecommandee(true);
        $this->assertTrue($this->offre->isEstRecommandee());
    }
}
