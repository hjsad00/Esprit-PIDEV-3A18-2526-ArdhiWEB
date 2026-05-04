<?php

namespace App\Tests\Service;

use App\Entity\EmployeTache\Employe;
use App\Service\EmployeTache\EmployeManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service EmployeManager.
 *
 * Ces tests vérifient les règles métier de l'entité Employe de manière
 * isolée, sans base de données ni kernel Symfony.
 *
 * Règles métier testées :
 *   1. Le nom est obligatoire
 *   2. Le prénom est obligatoire
 *   3. L'email doit être valide (format RFC)
 *   4. Le téléphone doit contenir exactement 8 chiffres
 *   5. Le salaire journalier ne peut pas être négatif
 *   6. Le type de contrat doit être parmi : CDI, CDD, Intérim
 *
 * Exécution : php bin/phpunit tests/Service/EmployeManagerTest.php
 */
class EmployeManagerTest extends TestCase
{
    private EmployeManager $manager;

    // ─── Configuration ────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->manager = new EmployeManager();
    }

    // ─── Helper privé ─────────────────────────────────────────────────────────

    /**
     * Crée un employé valide à utiliser comme base dans les tests.
     */
    private function creerEmployeValide(): Employe
    {
        $employe = new Employe();
        $employe->setNom('Ben Ali');
        $employe->setPrenom('Mohamed');
        $employe->setEmail('mohamed.benali@ardhiweb.tn');
        $employe->setTelephone('22334455');
        $employe->setSalaireJournalier(55.0);
        $employe->setTypeContrat('CDI');

        return $employe;
    }

    // ─── TEST 1 : Employé totalement valide ───────────────────────────────────

    /**
     * Un employé avec toutes les données correctes doit être validé sans erreur.
     */
    public function testEmployeValide(): void
    {
        $employe = $this->creerEmployeValide();

        $resultat = $this->manager->valider($employe);

        $this->assertTrue($resultat, 'Un employé avec des données valides doit être accepté.');
    }

    // ─── TEST 2 : Nom obligatoire ──────────────────────────────────────────────

    /**
     * Règle 1 : Un employé sans nom doit lever une InvalidArgumentException.
     */
    public function testNomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'employé est obligatoire.');

        $employe = $this->creerEmployeValide();
        $employe->setNom('');

        $this->manager->valider($employe);
    }

    /**
     * Règle 1 : Un nom composé uniquement d'espaces doit également être rejeté.
     */
    public function testNomEspacesSeulsRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setNom('     ');

        $this->manager->valider($employe);
    }

    // ─── TEST 3 : Prénom obligatoire ───────────────────────────────────────────

    /**
     * Règle 2 : Un employé sans prénom doit lever une InvalidArgumentException.
     */
    public function testPrenomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom de l\'employé est obligatoire.');

        $employe = $this->creerEmployeValide();
        $employe->setPrenom('');

        $this->manager->valider($employe);
    }

    // ─── TEST 4 : Email valide ─────────────────────────────────────────────────

    /**
     * Règle 3 : Un email sans arobase doit être rejeté.
     */
    public function testEmailSansArobaseRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'adresse email de l\'employé est invalide.');

        $employe = $this->creerEmployeValide();
        $employe->setEmail('email_invalide');

        $this->manager->valider($employe);
    }

    /**
     * Règle 3 : Un email sans domaine doit être rejeté.
     */
    public function testEmailSansDomainerejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setEmail('utilisateur@');

        $this->manager->valider($employe);
    }

    /**
     * Règle 3 : Un email correct avec sous-domaine doit être accepté.
     */
    public function testEmailCorrectAvecSousDomaine(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setEmail('ali.trabelsi@mail.ardhiweb.tn');

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 5 : Téléphone 8 chiffres ────────────────────────────────────────

    /**
     * Règle 4 : Un numéro de téléphone avec des lettres doit être rejeté.
     */
    public function testTelephoneAvecLettresRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le téléphone doit contenir exactement 8 chiffres.');

        $employe = $this->creerEmployeValide();
        $employe->setTelephone('1234ABCD');

        $this->manager->valider($employe);
    }

    /**
     * Règle 4 : Un numéro de téléphone trop court (7 chiffres) doit être rejeté.
     */
    public function testTelephoneTropCourtRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setTelephone('1234567');  // 7 chiffres seulement

        $this->manager->valider($employe);
    }

    /**
     * Règle 4 : Un numéro de téléphone trop long (9 chiffres) doit être rejeté.
     */
    public function testTelephoneTropLongRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setTelephone('123456789');  // 9 chiffres

        $this->manager->valider($employe);
    }

    /**
     * Règle 4 : Un numéro de téléphone de exactement 8 chiffres doit être accepté.
     */
    public function testTelephoneValide8Chiffres(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTelephone('98765432');

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 6 : Salaire journalier non négatif ──────────────────────────────

    /**
     * Règle 5 : Un salaire journalier négatif doit être rejeté.
     */
    public function testSalaireNegatifRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le salaire journalier ne peut pas être négatif.');

        $this->manager->validerSalaireJournalier(-10.0);
    }

    /**
     * Règle 5 : Un salaire de zéro est autorisé (stagiaire non rémunéré).
     */
    public function testSalaireZeroAccepte(): void
    {
        // Aucune exception attendue
        $this->manager->validerSalaireJournalier(0.0);
        $this->assertTrue(true); // Le test passe si aucune exception n'est levée
    }

    /**
     * Règle 5 : Un salaire positif est valide.
     */
    public function testSalairePositifAccepte(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setSalaireJournalier(120.500);

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 7 : Type de contrat autorisé ────────────────────────────────────

    /**
     * Règle 6 : Un type de contrat non reconnu doit être rejeté.
     */
    public function testTypeContratInvalideRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de contrat "Freelance" est invalide.');

        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('Freelance');

        $this->manager->valider($employe);
    }

    /**
     * Règle 6 : Le type "CDI" est valide.
     */
    public function testTypeContratCdiValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('CDI');

        $this->assertTrue($this->manager->valider($employe));
    }

    /**
     * Règle 6 : Le type "CDD" est valide.
     */
    public function testTypeContratCddValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('CDD');

        $this->assertTrue($this->manager->valider($employe));
    }

    /**
     * Règle 6 : Le type "Intérim" est valide.
     */
    public function testTypeContratInterimValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('Intérim');

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 8 : Helpers de l'entité ─────────────────────────────────────────

    /**
     * La méthode getNomComplet() doit retourner "Prénom Nom".
     */
    public function testNomComplet(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertEquals(
            'Mohamed Ben Ali',
            $employe->getNomComplet(),
            'getNomComplet() doit retourner "Prénom Nom".'
        );
    }

    /**
     * La méthode getInitiales() doit retourner les deux premières lettres en majuscule.
     */
    public function testInitiales(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertEquals(
            'MB',
            $employe->getInitiales(),
            'getInitiales() doit retourner "MB" pour Mohamed Ben Ali.'
        );
    }

    /**
     * hasPhoto() doit retourner false quand aucune photo n'est définie.
     */
    public function testHasPhotoFalseParDefaut(): void
    {
        $employe = new Employe();

        $this->assertFalse($employe->hasPhoto(), 'Sans photo, hasPhoto() doit retourner false.');
    }

    /**
     * hasPhoto() doit retourner true quand un chemin de photo est défini.
     */
    public function testHasPhotoTrueAvecPhoto(): void
    {
        $employe = new Employe();
        $employe->setPhotoPath('uploads/employes/photo_123.jpg');

        $this->assertTrue($employe->hasPhoto(), 'Avec une photo, hasPhoto() doit retourner true.');
    }

    /**
     * L'employé doit être actif par défaut à la création.
     */
    public function testActifParDefaut(): void
    {
        $employe = new Employe();

        $this->assertTrue($employe->isActif(), 'Un nouvel employé doit être actif par défaut.');
    }
}
