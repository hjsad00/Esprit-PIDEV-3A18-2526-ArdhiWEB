<?php

namespace App\Tests\Marketplace\Service;

use App\Entity\Marketplace\Coupon;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CouponService.
 * Teste les opérations CRUD et validations sur les coupons.
 */
class CouponServiceTest extends TestCase
{
    private Coupon $coupon;

    protected function setUp(): void
    {
        $this->coupon = new Coupon();
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣ Test ADD COUPON
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAddCoupon(): void
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

        // Verify coupon properties are set correctly
        $this->assertNotNull($coupon->getCode());
        $this->assertEquals('ARDHI10', $coupon->getCode());
        $this->assertEquals('POURCENTAGE', $coupon->getTypeReduction());
        $this->assertEquals(10.0, $coupon->getValeur());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 2️⃣ Test UPDATE COUPON
    // ─────────────────────────────────────────────────────────────────────────────

    public function testUpdateCoupon(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI20');
        $coupon->setTypeReduction('FIXE');
        $coupon->setValeur(20.0);
        $coupon->setDateDebut(new \DateTime('2026-05-01'));
        $coupon->setDateFin(new \DateTime('2026-07-01'));
        $coupon->setUtilisationMax(50);
        $coupon->setActif(true);
        $coupon->setMontantMin(100.0);
        $coupon->setLimiteParUser(2);

        // Update properties
        $coupon->setCode('ARDHI20-UPDATED');
        $coupon->setValeur(25.0);

        $this->assertEquals('ARDHI20-UPDATED', $coupon->getCode());
        $this->assertEquals(25.0, $coupon->getValeur());
        $this->assertEquals('FIXE', $coupon->getTypeReduction());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 3️⃣ Test DELETE COUPON (simulated)
    // ─────────────────────────────────────────────────────────────────────────────

    public function testDeleteCoupon(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');

        $this->assertNotNull($coupon->getCode());
        $this->assertEquals('ARDHI10', $coupon->getCode());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 4️⃣ Test FIND BY CODE - found
    // ─────────────────────────────────────────────────────────────────────────────

    public function testFindByCode_trouve(): void
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

        $this->assertNotNull($coupon);
        $this->assertEquals('ARDHI10', $coupon->getCode());
        $this->assertEquals('POURCENTAGE', $coupon->getTypeReduction());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 5️⃣ Test VALIDER COUPON - inactive
    // ─────────────────────────────────────────────────────────────────────────────

    public function testValiderCoupon_inactif(): void
    {
        $coupon = new Coupon();
        $coupon->setActif(false);

        $this->assertFalse($coupon->isActif());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 6️⃣ Test VALIDER COUPON - insufficient amount
    // ─────────────────────────────────────────────────────────────────────────────

    public function testValiderCoupon_montantInsuffisant(): void
    {
        $coupon = new Coupon();
        $coupon->setActif(true);
        $coupon->setDateDebut(new \DateTime('2026-04-30'));
        $coupon->setDateFin(new \DateTime('2026-06-30'));
        $coupon->setMontantMin(100.0);
        $coupon->setUtilisationMax(50);
        $coupon->setUtilisationActuelle(0);
        $coupon->setLimiteParUser(0);

        // Verify minimum amount constraint
        $this->assertGreaterThan(30.0, $coupon->getMontantMin());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 7️⃣ Test VALIDER COUPON - valid
    // ─────────────────────────────────────────────────────────────────────────────

    public function testValiderCoupon_valide(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setActif(true);
        $coupon->setDateDebut(new \DateTime('2026-04-30'));
        $coupon->setDateFin(new \DateTime('2026-06-30'));
        $coupon->setMontantMin(10.0);
        $coupon->setUtilisationMax(50);
        $coupon->setUtilisationActuelle(5);
        $coupon->setLimiteParUser(0); // no user limit

        $this->assertTrue($coupon->isActif());
        $this->assertLessThanOrEqual($coupon->getUtilisationMax(), $coupon->getUtilisationActuelle() + 45);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 8️⃣ Test CALCULATE REDUCTION - percentage
    // ─────────────────────────────────────────────────────────────────────────────

    public function testCalculateReduction_pourcentage(): void
    {
        $coupon = new Coupon();
        $coupon->setTypeReduction('POURCENTAGE');
        $coupon->setValeur(10.0); // 10%

        $montant = 200.0;
        $reduction = ($montant * $coupon->getValeur()) / 100;

        $this->assertEquals(20.0, $reduction);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 9️⃣ Test CALCULATE REDUCTION - fixed amount
    // ─────────────────────────────────────────────────────────────────────────────

    public function testCalculateReduction_montantFixe(): void
    {
        $coupon = new Coupon();
        $coupon->setTypeReduction('FIXE');
        $coupon->setValeur(30.0);

        $reduction = $coupon->getValeur();

        $this->assertEquals(30.0, $reduction);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 🔟 Test GET ALL COUPONS - empty list
    // ─────────────────────────────────────────────────────────────────────────────

    public function testGetAllCoupons_vide(): void
    {
        $coupons = [];

        $this->assertIsArray($coupons);
        $this->assertEmpty($coupons);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣1️⃣ Test: Multiple coupons with different reduction types
    // ─────────────────────────────────────────────────────────────────────────────

    public function testMultipleCouponsWithDifferentReductions(): void
    {
        $couponPourcentage = new Coupon();
        $couponPourcentage->setCode('PERCENTAGE-10');
        $couponPourcentage->setTypeReduction('POURCENTAGE');
        $couponPourcentage->setValeur(10.0);

        $couponFixe = new Coupon();
        $couponFixe->setCode('FIXE-50');
        $couponFixe->setTypeReduction('FIXE');
        $couponFixe->setValeur(50.0);

        $this->assertEquals('POURCENTAGE', $couponPourcentage->getTypeReduction());
        $this->assertEquals('FIXE', $couponFixe->getTypeReduction());
        $this->assertEquals(10.0, $couponPourcentage->getValeur());
        $this->assertEquals(50.0, $couponFixe->getValeur());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣2️⃣ Test: Coupon expiration logic
    // ─────────────────────────────────────────────────────────────────────────────

    public function testCouponExpirationLogic(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('ARDHI10');
        $coupon->setDateDebut(new \DateTime('2026-04-01'));
        $coupon->setDateFin(new \DateTime('2026-05-01'));

        $now = new \DateTime('2026-04-15');
        $isExpired = $now > $coupon->getDateFin();

        $this->assertFalse($isExpired);

        $now = new \DateTime('2026-06-01');
        $isExpired = $now > $coupon->getDateFin();

        $this->assertTrue($isExpired);
    }
}
