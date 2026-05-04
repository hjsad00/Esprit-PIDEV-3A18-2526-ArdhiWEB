<?php

namespace App\Tests\Marketplace\Service;

use App\Entity\Marketplace\Produits;
use App\Service\Marketplace\ProduitManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ProduitManager.
 *
 * Vérifie la logique métier liée aux produits (création, modification, 
 * suppression, gestion des stocks) en isolant la base de données via des mocks.
 */
class ProduitManagerTest extends TestCase
{
    private ProduitManager $produitManager;
    private $entityManagerMock;

    protected function setUp(): void
    {
        // Création du mock de l'EntityManager
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        // Instanciation du service avec le mock
        $this->produitManager = new ProduitManager($this->entityManagerMock);
    }

    private function creerProduit(int $id, string $nom, int $stock): Produits
    {
        $produit = new Produits();
        $produit->setNom($nom);
        $produit->setQuantiteStock($stock);
        // Ajout dynamique de l'ID pour le test
        $reflection = new \ReflectionClass($produit);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($produit, $id);

        return $produit;
    }

    // ─── TEST 1 : Création de produit ─────────────────────────────────────────

    public function testCreerProduit(): void
    {
        $produit = $this->creerProduit(1, 'Tracteur', 5);

        // L'EntityManager doit appeler persist et flush
        $this->entityManagerMock->expects($this->once())
            ->method('persist')
            ->with($produit);

        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        $this->produitManager->creerProduit($produit);
    }

    // ─── TEST 2 : Modification de produit ─────────────────────────────────────

    public function testModifierProduit(): void
    {
        $produit = $this->creerProduit(1, 'Semences de blé', 100);

        // L'EntityManager doit appeler flush
        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        $this->produitManager->modifierProduit($produit);
    }

    // ─── TEST 3 : Suppression de produit ──────────────────────────────────────

    public function testSupprimerProduit(): void
    {
        $produit = $this->creerProduit(1, 'Outil agricole', 10);

        // L'EntityManager doit appeler remove et flush
        $this->entityManagerMock->expects($this->once())
            ->method('remove')
            ->with($produit);

        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        $this->produitManager->supprimerProduit($produit);
    }

    // ─── TEST 4 : Gestion des stocks (Décrémentation) ─────────────────────────

    public function testDiminuerStockValide(): void
    {
        $produit = $this->creerProduit(1, 'Engrais Bio', 50);

        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        $resultat = $this->produitManager->diminuerStock($produit, 10);

        $this->assertTrue($resultat);
        $this->assertSame(40, $produit->getQuantiteStock());
    }

    public function testDiminuerStockInsuffisantLeveException(): void
    {
        $produit = $this->creerProduit(1, 'Engrais Bio', 5);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Stock insuffisant pour ce produit.');

        $this->produitManager->diminuerStock($produit, 10);
    }

    // ─── TEST 5 : Gestion des stocks (Incrémentation) ─────────────────────────

    public function testAjouterStock(): void
    {
        $produit = $this->creerProduit(1, 'Engrais Bio', 50);

        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        $this->produitManager->ajouterStock($produit, 20);

        $this->assertSame(70, $produit->getQuantiteStock());
    }
}
