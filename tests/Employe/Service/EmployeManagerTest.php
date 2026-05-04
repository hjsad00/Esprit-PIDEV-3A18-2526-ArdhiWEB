<?php

namespace App\Tests\Employe\Service;

use App\Entity\EmployeTache\Employe;
use App\Service\EmployeTache\EmployeManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service EmployeManager.
 *
 * Vérifie les 6 règles métier de validation d'un employé agricole,
 * sans base de données ni kernel Symfony.
 *
 * Règles testées :
 *   1. Le nom est obligatoire
 *   2. Le prénom est obligatoire
 *   3. L'email doit être valide (format RFC)
 *   4. Le téléphone doit contenir exactement 8 chiffres
 *   5. Le salaire journalier ne peut pas être négatif
 *   6. Le type de contrat doit être parmi : CDI, CDD, Intérim
 *
 * Exécution : php bin/phpunit tests/Employe/Service/EmployeManagerTest.php
 */
class EmployeManagerTest extends TestCase
{
    private EmployeManager $manager;

    // ─── Configuration ────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->manager = new EmployeManager();
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Crée un employé agricole valide (ouvrier spécialisé en irrigation).
     */
    private function creerEmployeValide(): Employe
    {
        $employe = new Employe();
        $employe->setNom('Ben Salah')
                ->setPrenom('Youssef')
                ->setEmail('youssef.bensalah@ferme-ardhiweb.tn')
                ->setTelephone('55441122')
                ->setPoste('Technicien irrigation')
                ->setSalaireJournalier(60.0)
                ->setTypeContrat('CDI')
                ->setIdAgriculteur(5);

        return $employe;
    }

    // ─── TEST 1 : Employé totalement valide ───────────────────────────────────

    /**
     * Un employé agricole avec toutes les données correctes doit être validé.
     */
    public function testEmployeValideRetourneTrue(): void
    {
        $employe = $this->creerEmployeValide();

        $this->assertTrue(
            $this->manager->valider($employe),
            'Un employé valide doit retourner true.'
        );
    }

    /**
     * Un employé en CDD est tout aussi valide qu'un CDI.
     */
    public function testEmployeSaisonnierCddValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('CDD')
                ->setPoste('Ouvrier saisonnier récolte');

        $this->assertTrue($this->manager->valider($employe));
    }

    /**
     * Un ouvrier en Intérim avec email correct est valide.
     */
    public function testEmployeInterimValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('Intérim')
                ->setEmail('interim.agri@rh-ardhiweb.tn');

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 2 : Règle 1 — Nom obligatoire ───────────────────────────────────

    /**
     * Un employé sans nom doit lever une InvalidArgumentException.
     */
    public function testNomVideLeveLException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'employé est obligatoire.');

        $employe = $this->creerEmployeValide();
        $employe->setNom('');

        $this->manager->valider($employe);
    }

    /**
     * Un nom composé d'espaces uniquement doit être rejeté.
     */
    public function testNomEspacesSeulsRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setNom('     ');

        $this->manager->valider($employe);
    }

    // ─── TEST 3 : Règle 2 — Prénom obligatoire ────────────────────────────────

    /**
     * Un employé sans prénom doit lever une InvalidArgumentException.
     */
    public function testPrenomVideLeveLException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom de l\'employé est obligatoire.');

        $employe = $this->creerEmployeValide();
        $employe->setPrenom('');

        $this->manager->valider($employe);
    }

    /**
     * Un prénom avec uniquement des espaces doit être rejeté.
     */
    public function testPrenomEspacesSeulsRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setPrenom('   ');

        $this->manager->valider($employe);
    }

    // ─── TEST 4 : Règle 3 — Email valide ──────────────────────────────────────

    /**
     * Un email sans arobase doit être rejeté.
     */
    public function testEmailSansArobaseRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'adresse email de l\'employé est invalide.');

        $employe = $this->creerEmployeValide();
        $employe->setEmail('youssef.ardhiweb.tn');

        $this->manager->valider($employe);
    }

    /**
     * Un email vide doit être rejeté.
     */
    public function testEmailVideRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setEmail('');

        $this->manager->valider($employe);
    }

    /**
     * Un email avec sous-domaine doit être accepté.
     */
    public function testEmailAvecSousDomainAccepte(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setEmail('rh.youssef@mail.ferme-nord.tn');

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 5 : Règle 4 — Téléphone 8 chiffres ─────────────────────────────

    /**
     * Un numéro avec des lettres doit être rejeté.
     */
    public function testTelephoneAvecLettresRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le téléphone doit contenir exactement 8 chiffres.');

        $employe = $this->creerEmployeValide();
        $employe->setTelephone('9988ABCD');

        $this->manager->valider($employe);
    }

    /**
     * Un numéro trop court (7 chiffres) doit être rejeté.
     */
    public function testTelephoneTropCourtRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setTelephone('9988776');

        $this->manager->valider($employe);
    }

    /**
     * Un numéro trop long (9 chiffres) doit être rejeté.
     */
    public function testTelephoneTropLongRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setTelephone('998877665');

        $this->manager->valider($employe);
    }

    /**
     * Un numéro de 8 chiffres valide doit être accepté.
     */
    public function testTelephone8ChiffresAccepte(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTelephone('74123456');

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 6 : Règle 5 — Salaire non négatif ──────────────────────────────

    /**
     * validerSalaireJournalier avec valeur négative doit lever une exception.
     */
    public function testSalaireNegatifRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le salaire journalier ne peut pas être négatif.');

        $this->manager->validerSalaireJournalier(-1.0);
    }

    /**
     * Un salaire de zéro est autorisé (stagiaire non rémunéré).
     */
    public function testSalaireZeroAccepte(): void
    {
        $this->manager->validerSalaireJournalier(0.0);
        $this->assertTrue(true); // passe si aucune exception
    }

    /**
     * Un salaire journalier positif typique d'un ouvrier agricole est valide.
     */
    public function testSalairePositifAccepte(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setSalaireJournalier(45.500);

        $this->assertTrue($this->manager->valider($employe));
    }

    // ─── TEST 7 : Règle 6 — Type de contrat autorisé ─────────────────────────

    /**
     * Un contrat "Freelance" n'est pas un type reconnu.
     */
    public function testTypeContratFreelanceRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de contrat "Freelance" est invalide.');

        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('Freelance');

        $this->manager->valider($employe);
    }

    /**
     * Un contrat non reconnu (chaîne vide) doit être rejeté.
     */
    public function testTypeContratVideRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('');

        $this->manager->valider($employe);
    }

    /**
     * CDI est un type de contrat valide pour un employé permanent.
     */
    public function testTypeContratCdiValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('CDI');

        $this->assertTrue($this->manager->valider($employe));
    }

    /**
     * CDD est valide pour un ouvrier saisonnier de récolte.
     */
    public function testTypeContratCddValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('CDD');

        $this->assertTrue($this->manager->valider($employe));
    }

    /**
     * Intérim est valide pour un renfort temporaire pendant les récoltes.
     */
    public function testTypeContratInterimValide(): void
    {
        $employe = $this->creerEmployeValide();
        $employe->setTypeContrat('Intérim');

        $this->assertTrue($this->manager->valider($employe));
    }

    /**
     * TYPES_CONTRAT_VALIDES doit contenir exactement CDI, CDD et Intérim.
     */
    public function testConstanteTypesContratValides(): void
    {
        $this->assertCount(3, EmployeManager::TYPES_CONTRAT_VALIDES);
        $this->assertContains('CDI',     EmployeManager::TYPES_CONTRAT_VALIDES);
        $this->assertContains('CDD',     EmployeManager::TYPES_CONTRAT_VALIDES);
        $this->assertContains('Intérim', EmployeManager::TYPES_CONTRAT_VALIDES);
    }

    // ─── TEST 8 : Validation individuelle des champs ──────────────────────────

    /**
     * validerNom() avec valeur valide ne doit pas lever d'exception.
     */
    public function testValiderNomValide(): void
    {
        $this->manager->validerNom('Ben Salah');
        $this->assertTrue(true);
    }

    /**
     * validerEmail() avec email valide ne doit pas lever d'exception.
     */
    public function testValiderEmailValide(): void
    {
        $this->manager->validerEmail('test@ferme.tn');
        $this->assertTrue(true);
    }

    /**
     * validerTelephone() avec un numéro valide ne doit pas lever d'exception.
     */
    public function testValiderTelephoneValide(): void
    {
        $this->manager->validerTelephone('98001234');
        $this->assertTrue(true);
    }
}
