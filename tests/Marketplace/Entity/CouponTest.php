<?php

namespace App\Tests\Marketplace\Entity;

use App\Entity\Marketplace\Coupon;
use PHPUnit\Framework\TestCase;

class CouponTest extends TestCase
{
    /**
     * 1️⃣ Test: Getters and Setters for basic properties
     */
    public function testGettersAndSetters(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setTypeReduction('POURCENTAGE');
        $coupon->setValeur(10.0);
        $coupon->setDateDebut(new \DateTime('2026-05-01'));
        $coupon->setDateFin(new \DateTime('2026-06-01'));
        $coupon->setUtilisationMax(100);
        $coupon->setUtilisationActuelle(0);
        $coupon->setActif(true);
        $coupon->setMontantMin(50.0);
        $coupon->setLimiteParUser(1);

        $this->assertEquals('ARDHI10', $coupon->getCode());
        $this->assertEquals('POURCENTAGE', $coupon->getTypeReduction());
        $this->assertEquals(10.0, $coupon->getValeur());
        $this->assertEquals(100, $coupon->getUtilisationMax());
        $this->assertEquals(0, $coupon->getUtilisationActuelle());
        $this->assertTrue($coupon->isActif());
        $this->assertEquals(50.0, $coupon->getMontantMin());
        $this->assertEquals(1, $coupon->getLimiteParUser());
    }

    /**
     * 2️⃣ Test: Coupon with fixed amount discount (MONTANT_FIXE)
     */
    public function testCouponWithFixedDiscount(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI20');
        $coupon->setTypeReduction('FIXE');
        $coupon->setValeur(20.0);
        $coupon->setDateDebut(new \DateTime('2026-05-01'));
        $coupon->setDateFin(new \DateTime('2026-07-01'));
        $coupon->setUtilisationMax(50);
        $coupon->setMontantMin(100.0);

        $this->assertEquals('FIXE', $coupon->getTypeReduction());
        $this->assertEquals(20.0, $coupon->getValeur());
        $this->assertEquals(50, $coupon->getUtilisationMax());
    }

    /**
     * 3️⃣ Test: Invalid discount percentage (> 100%)
     */
    public function testInvalidPercentageDiscount(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('INVALID');
        $coupon->setTypeReduction('POURCENTAGE');
        $coupon->setValeur(150.0); // Invalid: > 100%
        $coupon->setDateDebut(new \DateTime('2026-05-01'));
        $coupon->setDateFin(new \DateTime('2026-06-01'));

        // Should have invalid value
        $this->assertEquals(150.0, $coupon->getValeur());
    }

    /**
     * 4️⃣ Test: Invalid dates (end before start)
     */
    public function testInvalidDatesEndBeforeStart(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setTypeReduction('POURCENTAGE');
        $coupon->setValeur(10.0);
        $coupon->setDateDebut(new \DateTime('2026-06-01'));
        $coupon->setDateFin(new \DateTime('2026-05-01')); // Invalid: end before start

        $this->assertGreaterThan($coupon->getDateFin(), $coupon->getDateDebut());
    }

    /**
     * 5️⃣ Test: Coupon activation/deactivation
     */
    public function testCouponActivationDeactivation(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setActif(true);

        $this->assertTrue($coupon->isActif());

        $coupon->setActif(false);

        $this->assertFalse($coupon->isActif());
    }

    /**
     * 6️⃣ Test: Usage tracking (utilisationActuelle vs utilisationMax)
     */
    public function testUsageTracking(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setUtilisationMax(100);
        $coupon->setUtilisationActuelle(0);

        // Initial state
        $this->assertEquals(0, $coupon->getUtilisationActuelle());
        $this->assertEquals(100, $coupon->getUtilisationMax());
        $this->assertLessThanOrEqual($coupon->getUtilisationMax(), $coupon->getUtilisationActuelle() + 100);

        // Simulate usage
        $coupon->setUtilisationActuelle(50);
        $this->assertEquals(50, $coupon->getUtilisationActuelle());
        $this->assertLessThanOrEqual($coupon->getUtilisationMax(), $coupon->getUtilisationActuelle() + 50);
    }

    /**
     * 7️⃣ Test: Minimum purchase amount constraint
     */
    public function testMinimumPurchaseAmount(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI50');
        $coupon->setMontantMin(100.0);

        $this->assertEquals(100.0, $coupon->getMontantMin());
    }

    /**
     * 8️⃣ Test: Limit per user
     */
    public function testLimitPerUser(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setLimiteParUser(2);

        $this->assertEquals(2, $coupon->getLimiteParUser());
    }

    /**
     * 9️⃣ Test: Coupon with zero limit per user (unlimited)
     */
    public function testUnlimitedLimitPerUser(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setLimiteParUser(0); // Unlimited

        $this->assertEquals(0, $coupon->getLimiteParUser());
    }

    /**
     * 🔟 Test: Default values
     */
    public function testDefaultValues(): void
    {
        $coupon = new Coupon();

        $this->assertTrue($coupon->isActif());
        $this->assertEquals(0, $coupon->getMontantMin());
        $this->assertEquals(1, $coupon->getLimiteParUser());
        $this->assertEquals(0, $coupon->getUtilisationActuelle());
    }
}
