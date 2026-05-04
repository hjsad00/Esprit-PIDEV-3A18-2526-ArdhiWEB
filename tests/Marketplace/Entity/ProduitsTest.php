<?php

namespace App\Tests\Marketplace\Entity;

use App\Entity\Marketplace\Produits;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests unitaires de l'entité Produits.
 *
 * Vérifie les getters/setters, les valeurs par défaut et
 * les contraintes de validation (Assert) du composant Validator.
 *
 * Exécution : php bin/phpunit tests/Marketplace/Entity/ProduitsTest.php
 */
class ProduitsTest extends KernelTestCase
{
    // ─── Helper ───────────────────────────────────────────────────────────────

    private function creerProduitValide(): Produits
    {
        $produit = new Produits();
        $produit->setNom('Huile d\'olive Extra Vierge')
                ->setPrix(25.5)
                ->setQuantiteStock(10)
                ->setCategorie('Agricole')
                ->setUniteMesure('L')
                ->setVisible(true)
                ->setVisibleAdmin(true);

        return $produit;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function testProduitValide(): void
    {
        self::bootKernel();
        $validator = static::getContainer()->get('validator');

        $produit = $this->creerProduitValide();
        
        // Assertions de base
        $this->assertEquals('Huile d\'olive Extra Vierge', $produit->getNom());
        $this->assertEquals(25.5, $produit->getPrix());
        $this->assertEquals(10, $produit->getQuantiteStock());

        // Validation Symfony
        $errors = $validator->validate($produit);
        $this->assertCount(0, $errors, 'Le produit doit être valide et ne retourner aucune erreur de validation.');
    }

    public function testProduitInvalidePrixNegatifOuNul(): void
    {
        self::bootKernel();
        $validator = static::getContainer()->get('validator');

        // Test avec un prix invalide (ex: 0 au lieu de minimum 0.1)
        $produit = $this->creerProduitValide()->setPrix(0);
        $errors = $validator->validate($produit);

        $this->assertGreaterThan(0, count($errors), 'Une erreur doit être levée car le prix n\'est pas supérieur à 0.1');

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getMessage();
        }
        $this->assertContains('Le prix doit être supérieur à 0.1 DT.', $messages);
    }

    public function testProduitInvalideStockZero(): void
    {
        self::bootKernel();
        $validator = static::getContainer()->get('validator');

        // Test avec un stock invalide (ex: 0)
        $produit = $this->creerProduitValide()->setQuantiteStock(0);
        $errors = $validator->validate($produit);

        $this->assertGreaterThan(0, count($errors), 'Une erreur doit être levée car le stock n\'est pas >= 1');
        
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getMessage();
        }
        $this->assertContains('Le stock doit être supérieur à 0.', $messages);
    }
    
    public function testProduitInvalideNomVide(): void
    {
        self::bootKernel();
        $validator = static::getContainer()->get('validator');

        $produit = $this->creerProduitValide()->setNom('');
        $errors = $validator->validate($produit);

        $this->assertGreaterThan(0, count($errors), 'Une erreur doit être levée car le nom est vide.');
    }
}
