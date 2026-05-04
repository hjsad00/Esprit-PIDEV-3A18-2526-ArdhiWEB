<?php

namespace App\Tests\UserAndDiag\Entity;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\User;
use PHPUnit\Framework\TestCase;

class DiagnosticTest extends TestCase
{
    private Diagnostic $diagnostic;

    protected function setUp(): void
    {
        $this->diagnostic = new Diagnostic();
    }

    public function testConstructorSetsDateScan(): void
    {
        $this->assertNotNull($this->diagnostic->getDateScan());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->diagnostic->getDateScan());
    }

    public function testDefaultConfiance(): void
    {
        $this->assertSame(0.0, $this->diagnostic->getConfiance());
    }

    public function testGetSetImageScannee(): void
    {
        $this->diagnostic->setImageScannee('https://imgbb.com/scan123.jpg');
        $this->assertSame('https://imgbb.com/scan123.jpg', $this->diagnostic->getImageScannee());
    }

    public function testGetSetResultatIa(): void
    {
        $this->diagnostic->setResultatIa('Tomate - Mildiou');
        $this->assertSame('Tomate - Mildiou', $this->diagnostic->getResultatIa());
    }

    public function testGetSetConfiance(): void
    {
        $this->diagnostic->setConfiance(92.5);
        $this->assertSame(92.5, $this->diagnostic->getConfiance());
    }

    public function testGetSetUser(): void
    {
        $user = new User();
        $user->setEmail('farmer@test.com');

        $this->diagnostic->setUser($user);
        $this->assertSame($user, $this->diagnostic->getUser());
    }

    public function testGetSetCoordinates(): void
    {
        $this->diagnostic->setLatitude(36.8065);
        $this->diagnostic->setLongitude(10.1815);

        $this->assertSame(36.8065, $this->diagnostic->getLatitude());
        $this->assertSame(10.1815, $this->diagnostic->getLongitude());
    }

    public function testGetSetLocationLabel(): void
    {
        $this->diagnostic->setLocationLabel('Tunis, Tunisie');
        $this->assertSame('Tunis, Tunisie', $this->diagnostic->getLocationLabel());
    }

    public function testGetSetSeverity(): void
    {
        $this->diagnostic->setSeverity('CRITICAL');
        $this->assertSame('CRITICAL', $this->diagnostic->getSeverity());
    }

    public function testNullableFields(): void
    {
        $diag = new Diagnostic();
        $this->assertNull($diag->getUser());
        $this->assertNull($diag->getLatitude());
        $this->assertNull($diag->getLongitude());
        $this->assertNull($diag->getLocationLabel());
        $this->assertNull($diag->getSeverity());
        $this->assertNull($diag->getImageScannee());
        $this->assertNull($diag->getResultatIa());
    }
}
