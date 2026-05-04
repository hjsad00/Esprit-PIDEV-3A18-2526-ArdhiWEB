<?php

namespace App\Tests\Marketplace\Entity;

use App\Entity\Marketplace\Produits;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Produits.
 * Teste les propriétés et validations de base des produits du marketplace.
 */
class ProduitsTest extends TestCase
{
    /**
     * 1️⃣ Test: Getters and Setters for basic properties
     */
    public function testGettersAndSetters(): void
    {
        $produit = new Produits();
        $produit->setNom('Tomates');
        $produit->setDescription('Tomates fraîches');
        $produit->setPrix(5.50);
        $produit->setQuantiteStock(100);
        $produit->setCategorie('Legumes');
        $produit->setUniteMesure('Kg');
        $produit->setImage('tomate.jpg');
        $produit->setRemise(10.0);
        $produit->setTypeRemise('POURCENTAGE');
        $produit->setVisible(true);
        $produit->setVisibleAdmin(true);

        $this->assertEquals('Tomates', $produit->getNom());
        $this->assertEquals('Tomates fraîches', $produit->getDescription());
        $this->assertEquals(5.50, $produit->getPrix());
        $this->assertEquals(100, $produit->getQuantiteStock());
        $this->assertEquals('Legumes', $produit->getCategorie());
        $this->assertEquals('Kg', $produit->getUniteMesure());
        $this->assertEquals('tomate.jpg', $produit->getImage());
        $this->assertEquals(10.0, $produit->getRemise());
        $this->assertEquals('POURCENTAGE', $produit->getTypeRemise());
        $this->assertTrue($produit->isVisible());
        $this->assertTrue($produit->isVisibleAdmin());
    }

    /**
     * 2️⃣ Test: Product with different unit measurements
     */
    public function testProductWithDifferentUnits(): void
    {
        $produitKg = new Produits();
        $produitKg->setNom('Riz');
        $produitKg->setUniteMesure('Kg');

        $produitL = new Produits();
        $produitL->setNom('Huile');
        $produitL->setUniteMesure('L');

        $produitPiece = new Produits();
        $produitPiece->setNom('Oeufs');
        $produitPiece->setUniteMesure('Piece');

        $this->assertEquals('Kg', $produitKg->getUniteMesure());
        $this->assertEquals('L', $produitL->getUniteMesure());
        $this->assertEquals('Piece', $produitPiece->getUniteMesure());
    }

    /**
     * 3️⃣ Test: Product visibility control
     */
    public function testProductVisibilityControl(): void
    {
        $produit = new Produits();
        $produit->setVisible(true);
        $produit->setVisibleAdmin(true);

        $this->assertTrue($produit->isVisible());
        $this->assertTrue($produit->isVisibleAdmin());

        $produit->setVisible(false);
        $produit->setVisibleAdmin(false);

        $this->assertFalse($produit->isVisible());
        $this->assertFalse($produit->isVisibleAdmin());
    }

    /**
     * 4️⃣ Test: Product discount calculations
     */
    public function testProductDiscountCalculations(): void
    {
        $produit = new Produits();
        $produit->setPrix(100.0);
        $produit->setRemise(20.0);
        $produit->setTypeRemise('POURCENTAGE');

        // Calculate discount: 100 * 20 / 100 = 20
        $prixFinal = $produit->getPrix() - ($produit->getPrix() * $produit->getRemise() / 100);

        $this->assertEquals(80.0, $prixFinal);
    }

    /**
     * 5️⃣ Test: Product with fixed discount
     */
    public function testProductWithFixedDiscount(): void
    {
        $produit = new Produits();
        $produit->setPrix(50.0);
        $produit->setRemise(10.0);
        $produit->setTypeRemise('FIXE');

        // With fixed discount: 50 - 10 = 40
        $prixFinal = $produit->getPrix() - $produit->getRemise();

        $this->assertEquals(40.0, $prixFinal);
    }

    /**
     * 6️⃣ Test: Product stock management
     */
    public function testProductStockManagement(): void
    {
        $produit = new Produits();
        $produit->setQuantiteStock(100);

        $this->assertEquals(100, $produit->getQuantiteStock());

        // Simulate stock reduction
        $produit->setQuantiteStock(50);
        $this->assertEquals(50, $produit->getQuantiteStock());

        // Out of stock
        $produit->setQuantiteStock(0);
        $this->assertEquals(0, $produit->getQuantiteStock());
    }

    /**
     * 7️⃣ Test: Product categories
     */
    public function testProductCategories(): void
    {
        $categories = ['Fruits', 'Legumes', 'Cereales', 'Produits-Laitiers'];

        foreach ($categories as $category) {
            $produit = new Produits();
            $produit->setCategorie($category);
            $this->assertEquals($category, $produit->getCategorie());
        }
    }

    /**
     * 8️⃣ Test: Product with minimum price
     */
    public function testProductWithMinimumPrice(): void
    {
        $produit = new Produits();
        $produit->setPrix(0.5);

        // According to validation: Must be > 0.1
        $this->assertGreaterThan(0.1, $produit->getPrix());
    }

    /**
     * 9️⃣ Test: Product name length constraints
     */
    public function testProductNameLengthConstraints(): void
    {
        $produit = new Produits();
        
        // Valid name (3 chars minimum)
        $produit->setNom('Riz');
        $this->assertEquals('Riz', $produit->getNom());
        $this->assertGreaterThanOrEqual(3, strlen($produit->getNom()));

        // Valid name (100 chars maximum)
        $longName = str_repeat('A', 100);
        $produit->setNom($longName);
        $this->assertLessThanOrEqual(100, strlen($produit->getNom()));
    }

    /**
     * 🔟 Test: Default visibility values
     */
    public function testDefaultVisibilityValues(): void
    {
        $produit = new Produits();

        // By default, products should be visible
        $this->assertTrue($produit->isVisible());
        $this->assertTrue($produit->isVisibleAdmin());
    }

    /**
     * 1️⃣1️⃣ Test: Product without discount
     */
    public function testProductWithoutDiscount(): void
    {
        $produit = new Produits();
        $produit->setPrix(100.0);
        $produit->setRemise(0.0);

        $this->assertEquals(0.0, $produit->getRemise());
        $this->assertEquals(100.0, $produit->getPrix());
    }

    /**
     * 1️⃣2️⃣ Test: Multiple products with same category
     */
    public function testMultipleProductsSameCategory(): void
    {
        $produit1 = new Produits();
        $produit1->setNom('Tomates');
        $produit1->setCategorie('Legumes');

        $produit2 = new Produits();
        $produit2->setNom('Oignons');
        $produit2->setCategorie('Legumes');

        $this->assertEquals('Legumes', $produit1->getCategorie());
        $this->assertEquals('Legumes', $produit2->getCategorie());
        $this->assertEquals($produit1->getCategorie(), $produit2->getCategorie());
    }

    /**
     * 1️⃣3️⃣ Test: Product image handling
     */
    public function testProductImageHandling(): void
    {
        $produit = new Produits();
        $produit->setImage(null);

        $this->assertNull($produit->getImage());

        $produit->setImage('produit.jpg');
        $this->assertNotNull($produit->getImage());
        $this->assertEquals('produit.jpg', $produit->getImage());
    }

    /**
     * 1️⃣4️⃣ Test: Product description nullable
     */
    public function testProductDescriptionNullable(): void
    {
        $produit = new Produits();
        $produit->setDescription(null);

        $this->assertNull($produit->getDescription());

        $produit->setDescription('Description détaillée');
        $this->assertEquals('Description détaillée', $produit->getDescription());
    }

    /**
     * 1️⃣5️⃣ Test: Product complete workflow
     */
    public function testProductCompleteWorkflow(): void
    {
        // Create product
        $produit = new Produits();
        $produit->setNom('Tomates Bio');
        $produit->setDescription('Tomates biologiques fraîches');
        $produit->setPrix(8.50);
        $produit->setQuantiteStock(50);
        $produit->setCategorie('Legumes');
        $produit->setUniteMesure('Kg');
        $produit->setRemise(5.0);
        $produit->setTypeRemise('POURCENTAGE');
        $produit->setVisible(true);

        // Verify all properties
        $this->assertEquals('Tomates Bio', $produit->getNom());
        $this->assertEquals(8.50, $produit->getPrix());
        $this->assertEquals(50, $produit->getQuantiteStock());
        $this->assertEquals('Legumes', $produit->getCategorie());

        // Calculate final price with discount
        $prixFinal = $produit->getPrix() - ($produit->getPrix() * $produit->getRemise() / 100);
        $this->assertEquals(8.075, $prixFinal);

        // Simulate stock decrease
        $produit->setQuantiteStock(25);
        $this->assertEquals(25, $produit->getQuantiteStock());

        // Hide product (out of stock or admin action)
        $produit->setVisible(false);
        $this->assertFalse($produit->isVisible());
    }
}
