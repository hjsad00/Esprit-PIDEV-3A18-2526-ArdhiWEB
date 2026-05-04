<?php

namespace App\Tests\Employe\Entity;

use App\Entity\EmployeTache\Employe;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Employe.
 *
 * Vérifie les getters/setters, les valeurs par défaut et
 * les méthodes utilitaires sans dépendance à la base de données.
 *
 * Contexte agricole : ouvriers, irrigateurs, conducteurs de tracteur, etc.
 *
 * Exécution : php bin/phpunit tests/Employe/Entity/EmployeTest.php
 */
class EmployeTest extends TestCase
{
    // ─── Helper ───────────────────────────────────────────────────────────────

    private function creerEmployeValide(): Employe
    {
        $employe = new Employe();
        $employe->setNom('Trabelsi')
                ->setPrenom('Khaled')
                ->setEmail('khaled.trabelsi@ferme-ardhiweb.tn')
                ->setTelephone('29887712')
                ->setPoste('Ouvrier agricole')
                ->setSalaireJournalier(45.500)
                ->setTypeContrat('CDI')
                ->setDateEmbauche(new \DateTime('2021-03-15'))
                ->setIdAgriculteur(7);

        return $employe;
    }

    // ─── TEST 1 : Valeurs par défaut à la création ────────────────────────────

    /**
     * Un employé nouvellement créé doit être actif par défaut.
     */
    public function testActifParDefaut(): void
    {
        $employe = new Employe();

        $this->assertTrue(
            $employe->isActif(),
            'Un nouvel employé doit être actif par défaut.'
        );
    }

    /**
     * Le salaire journalier par défaut est 40.0 DT.
     */
    public function testSalaireJournalierParDefaut(): void
    {
        $employe = new Employe();

        $this->assertSame(40.0, $employe->getSalaireJournalier());
    }

    /**
     * Le type de contrat par défaut est CDI.
     */
    public function testTypeContratParDefaut(): void
    {
        $employe = new Employe();

        $this->assertSame('CDI', $employe->getTypeContrat());
    }

    /**
     * Sans photo, hasPhoto() doit retourner false.
     */
    public function testHasPhotoFalseParDefaut(): void
    {
        $employe = new Employe();

        $this->assertFalse($employe->hasPhoto());
    }

    // ─── TEST 2 : Getters / Setters de base ───────────────────────────────────

    /**
     * Les champs textuels doivent être correctement persistés via les setters.
     */
    public function testGettersSettersBase(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertSame('Trabelsi', $employe->getNom());
        $this->assertSame('Khaled', $employe->getPrenom());
        $this->assertSame('khaled.trabelsi@ferme-ardhiweb.tn', $employe->getEmail());
        $this->assertSame('29887712', $employe->getTelephone());
        $this->assertSame('Ouvrier agricole', $employe->getPoste());
        $this->assertSame(7, $employe->getIdAgriculteur());
    }

    /**
     * setActif(false) doit désactiver l'employé (ex. fin de saison agricole).
     */
    public function testSetActifFalse(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setActif(false);

        $this->assertFalse($employe->isActif());
    }

    /**
     * setSalaireJournalier doit persister la valeur fournie.
     */
    public function testSetSalaireJournalier(): void
    {
        $employe = new Employe();
        $employe->setSalaireJournalier(65.750);

        $this->assertEqualsWithDelta(65.750, $employe->getSalaireJournalier(), 0.001);
    }

    /**
     * setSalaireJournalier avec une valeur négative doit être ramenée à 0
     * (la règle métier est dans le setter lui-même : max(0, salaire)).
     */
    public function testSetSalaireNegatifPlafonnéA0(): void
    {
        $employe = new Employe();
        $employe->setSalaireJournalier(-20.0);

        $this->assertSame(0.0, $employe->getSalaireJournalier());
    }

    /**
     * setTypeContrat doit persister la chaîne fournie.
     */
    public function testSetTypeContrat(): void
    {
        $employe = new Employe();
        $employe->setTypeContrat('CDD');

        $this->assertSame('CDD', $employe->getTypeContrat());
    }

    /**
     * setDateEmbauche doit persister la date (ouvrier saisonnier embauché en mars).
     */
    public function testSetDateEmbauche(): void
    {
        $date    = new \DateTime('2023-03-01');
        $employe = new Employe();
        $employe->setDateEmbauche($date);

        $this->assertSame($date, $employe->getDateEmbauche());
    }

    /**
     * setDateEmbauche(null) doit être accepté.
     */
    public function testSetDateEmbaucheNull(): void
    {
        $employe = new Employe();
        $employe->setDateEmbauche(null);

        $this->assertNull($employe->getDateEmbauche());
    }

    /**
     * setPhotoPath doit persister le chemin de la photo.
     */
    public function testSetPhotoPath(): void
    {
        $employe = new Employe();
        $employe->setPhotoPath('uploads/employes/khaled_trabelsi.jpg');

        $this->assertSame('uploads/employes/khaled_trabelsi.jpg', $employe->getPhotoPath());
        $this->assertTrue($employe->hasPhoto());
    }

    /**
     * setQrCodeUnique doit persister le code.
     */
    public function testSetQrCodeUnique(): void
    {
        $employe = new Employe();
        $employe->setQrCodeUnique('EMP_42_AB12CD');

        $this->assertSame('EMP_42_AB12CD', $employe->getQrCodeUnique());
    }

    // ─── TEST 3 : Méthodes utilitaires ────────────────────────────────────────

    /**
     * getNomComplet() doit retourner "Prénom Nom".
     */
    public function testNomComplet(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertSame('Khaled Trabelsi', $employe->getNomComplet());
    }

    /**
     * getInitiales() doit retourner les initiales en majuscules.
     * Ex : Khaled Trabelsi → "KT"
     */
    public function testInitiales(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertSame('KT', $employe->getInitiales());
    }

    /**
     * __toString() doit retourner le nom complet.
     */
    public function testToString(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertSame('Khaled Trabelsi', (string) $employe);
    }

    /**
     * hasPhoto() doit retourner true lorsqu'un chemin est défini.
     */
    public function testHasPhotoTrue(): void
    {
        $employe = new Employe();
        $employe->setPhotoPath('uploads/employes/photo.png');

        $this->assertTrue($employe->hasPhoto());
    }

    /**
     * Deux employés distincts doivent avoir des initiales différentes
     * (irrigateur vs récolteuse — contexte parcelle).
     */
    public function testInitialesDifferentes(): void
    {
        $irrigateur = new Employe();
        $irrigateur->setNom('Gharbi')->setPrenom('Sami');

        $recolteuse = new Employe();
        $recolteuse->setNom('Mansouri')->setPrenom('Fatma');

        $this->assertNotSame($irrigateur->getInitiales(), $recolteuse->getInitiales());
    }
}
