<?php

namespace App\Tests\Marketplace\Entity;

use App\Entity\Marketplace\Produits;
use App\Entity\UserAndDiag\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitaires de l'entité Produits.
 *
 * Vérifie les getters/setters, les valeurs par défaut et
 * la validation des données (nom, prix, stock, etc.).
 *
 * Contexte agricole : semences, engrais, outils, etc.
 */
class ProduitTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function creerProduitValide(): Produits
    {
        $user = new User();
        
        $produit = new Produits();
        $produit->setNom('Engrais Bio NPK')
                ->setDescription('Engrais naturel pour une meilleure croissance des cultures.')
                ->setPrix(45.50)
                ->setQuantiteStock(150)
                ->setCategorie('Engrais')
                ->setUniteMesure('Kg')
                ->setUser($user)
                ->setVisible(true);

        return $produit;
    }

    // ─── TEST 1 : Getters / Setters ───────────────────────────────────────────

    public function testGettersSettersBase(): void
    {
        $produit = $this->creerProduitValide();

        $this->assertSame('Engrais Bio NPK', $produit->getNom());
        $this->assertSame('Engrais naturel pour une meilleure croissance des cultures.', $produit->getDescription());
        $this->assertSame(45.50, $produit->getPrix());
        $this->assertSame(150, $produit->getQuantiteStock());
        $this->assertSame('Engrais', $produit->getCategorie());
        $this->assertSame('Kg', $produit->getUniteMesure());
        $this->assertTrue($produit->isVisible());
    }

    // ─── TEST 2 : Validation Nom ──────────────────────────────────────────────

    public function testNomValide(): void
    {
        $produit = $this->creerProduitValide();
        $erreurs = $this->validator->validate($produit);
        
        $this->assertCount(0, $erreurs);
    }

    public function testNomVideRejete(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setNom('');

        $erreurs = $this->validator->validate($produit);
        $this->assertGreaterThan(0, count($erreurs));
    }

    // ─── TEST 3 : Validation Prix ─────────────────────────────────────────────

    public function testPrixInvalideRejete(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setPrix(0.0); // Le prix doit être > 0.1 selon les asserts

        $erreurs = $this->validator->validate($produit);
        $this->assertGreaterThan(0, count($erreurs));
    }

    // ─── TEST 4 : Validation Stock ────────────────────────────────────────────

    public function testStockNegatifOuNulRejete(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setQuantiteStock(0); // Le stock doit être >= 1

        $erreurs = $this->validator->validate($produit);
        $this->assertGreaterThan(0, count($erreurs));
    }

    // ─── TEST 5 : Validation Unité de Mesure ──────────────────────────────────

    public function testUniteMesureInvalideRejetee(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setUniteMesure('Gramme'); // Unité non autorisée ('Kg', 'L', 'Piece')

        $erreurs = $this->validator->validate($produit);
        $this->assertGreaterThan(0, count($erreurs));
    }

    public function testUnitesMesureValidesAcceptees(): void
    {
        $produit = $this->creerProduitValide();
        
        $produit->setUniteMesure('L');
        $this->assertCount(0, $this->validator->validate($produit));
        
        $produit->setUniteMesure('Piece');
        $this->assertCount(0, $this->validator->validate($produit));
    }

    // ─── TEST 6 : Validation Remise ───────────────────────────────────────────

    public function testRemisePourcentageValide(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setTypeRemise('POURCENTAGE');
        $produit->setRemise(15.0); // 15%

        $erreurs = $this->validator->validate($produit);
        $this->assertCount(0, $erreurs);
    }

    public function testRemisePourcentageInvalideRejetee(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setTypeRemise('POURCENTAGE');
        $produit->setRemise(150.0); // > 100%

        $erreurs = $this->validator->validate($produit);
        $this->assertGreaterThan(0, count($erreurs));
    }

    public function testRemiseFixeSuperieureAuPrixRejetee(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setPrix(50.0);
        $produit->setTypeRemise('FIXE');
        $produit->setRemise(60.0); // Remise > Prix

        $erreurs = $this->validator->validate($produit);
        $this->assertGreaterThan(0, count($erreurs));
    }

    // ─── TEST 7 : Calculs de Prix ─────────────────────────────────────────────

    public function testCalculPrixFinalAvecRemisePourcentage(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setPrix(100.0);
        $produit->setTypeRemise('POURCENTAGE');
        $produit->setRemise(20.0); // 20%

        $this->assertEqualsWithDelta(80.0, $produit->getPrixFinal(), 0.01);
    }

    public function testCalculPrixFinalAvecRemiseFixe(): void
    {
        $produit = $this->creerProduitValide();
        $produit->setPrix(100.0);
        $produit->setTypeRemise('FIXE');
        $produit->setRemise(15.0); // 15 DT

        $this->assertEqualsWithDelta(85.0, $produit->getPrixFinal(), 0.01);
    }
}
