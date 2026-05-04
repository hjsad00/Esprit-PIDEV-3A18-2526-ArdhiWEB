<?php

namespace App\Tests\Employe\Service;

use App\Entity\EmployeTache\Tache;
use App\Service\EmployeTache\TacheManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service TacheManager.
 *
 * Vérifie les règles métier de gestion des tâches agricoles,
 * sans base de données ni kernel Symfony.
 *
 * Règles testées :
 *   1. Le titre est obligatoire
 *   2. Le statut doit être dans la liste autorisée
 *   3. La priorité doit être entre 1 et 4
 *   4. La catégorie doit être une catégorie agricole reconnue
 *   5. La date de fin ne peut pas être antérieure à la date de début
 *   6. Affectation d'employé (machine d'état)
 *   7. Cycle de vie : En cours → Terminé → Validé
 *
 * Exécution : php bin/phpunit tests/Employe/Service/TacheManagerTest.php
 */
class TacheManagerTest extends TestCase
{
    private TacheManager $manager;

    // ─── Configuration ────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->manager = new TacheManager();
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Crée une tâche agricole valide utilisable comme base dans les tests.
     * Contexte : irrigation d'une parcelle blé, priorité haute.
     */
    private function creerTacheValide(): Tache
    {
        $tache = new Tache();
        $tache->setTitre('Irrigation parcelle blé sud')
              ->setDescription('Arrosage quotidien, zone S3, débit 4L/min.')
              ->setStatut(Tache::STATUT_EN_ATTENTE)
              ->setPriorite(3)
              ->setCategorie('Irrigation')
              ->setDateDebut(new \DateTime('today'))
              ->setDateFin(new \DateTime('+5 days'))
              ->setIdAgriculteur(7)
              ->setIdEmploye(null);

        return $tache;
    }

    // ─── TEST 1 : Tâche totalement valide ─────────────────────────────────────

    /**
     * Une tâche agricole avec toutes les données correctes doit être validée.
     */
    public function testTacheValideRetourneTrue(): void
    {
        $tache = $this->creerTacheValide();

        $this->assertTrue(
            $this->manager->valider($tache),
            'Une tâche valide doit retourner true.'
        );
    }

    /**
     * Une tâche de récolte critique sans dates doit aussi être valide.
     */
    public function testTacheSansDateEstValide(): void
    {
        $tache = new Tache();
        $tache->setTitre('Récolte olives domaine nord')
              ->setStatut(Tache::STATUT_EN_ATTENTE)
              ->setPriorite(4)
              ->setCategorie('Récolte');

        $this->assertTrue($this->manager->valider($tache));
    }

    // ─── TEST 2 : Règle 1 — Titre obligatoire ─────────────────────────────────

    /**
     * Un titre vide doit lever une InvalidArgumentException.
     */
    public function testTitreVideLeveLException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre de la tâche est obligatoire.');

        $tache = $this->creerTacheValide();
        $tache->setTitre('');

        $this->manager->valider($tache);
    }

    /**
     * Un titre composé uniquement d'espaces doit être rejeté.
     */
    public function testTitreEspacesSeulsRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tache = $this->creerTacheValide();
        $tache->setTitre('     ');

        $this->manager->valider($tache);
    }

    /**
     * validerTitre() avec une valeur valide ne lève pas d'exception.
     */
    public function testValiderTitreValide(): void
    {
        $this->manager->validerTitre('Fertilisation parcelle A7');
        $this->assertTrue(true);
    }

    // ─── TEST 3 : Règle 2 — Statut valide ─────────────────────────────────────

    /**
     * Un statut non reconnu doit lever une InvalidArgumentException.
     */
    public function testStatutInvalideRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut "En retard" est invalide.');

        $tache = $this->creerTacheValide();
        $tache->setStatut('En retard');

        $this->manager->valider($tache);
    }

    /**
     * Tous les statuts du tableau STATUTS doivent être acceptés.
     */
    public function testTousLesStatutsValides(): void
    {
        foreach (Tache::STATUTS as $statut) {
            $tache = $this->creerTacheValide();
            $tache->setStatut($statut);

            $this->assertTrue(
                $this->manager->valider($tache),
                "Le statut '$statut' devrait être valide."
            );
        }
    }

    // ─── TEST 4 : Règle 3 — Priorité entre 1 et 4 ────────────────────────────

    /**
     * Une priorité à 0 (hors plage) doit être rejetée.
     */
    public function testPriorite0Rejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La priorité doit être un entier entre 1 (Basse) et 4 (Critique).');

        $tache = $this->creerTacheValide();
        $tache->setPriorite(0);

        $this->manager->valider($tache);
    }

    /**
     * Une priorité à 5 (hors plage) doit être rejetée.
     */
    public function testPriorite5Rejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tache = $this->creerTacheValide();
        $tache->setPriorite(5);

        $this->manager->valider($tache);
    }

    /**
     * Une priorité null doit être rejetée.
     */
    public function testPrioriteNullRejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tache = $this->creerTacheValide();
        $tache->setPriorite(null);

        $this->manager->valider($tache);
    }

    /**
     * Les priorités 1 à 4 sont toutes valides.
     */
    public function testToutesLesPrioritesValides(): void
    {
        foreach (array_keys(Tache::PRIORITES) as $niveau) {
            $tache = $this->creerTacheValide();
            $tache->setPriorite($niveau);

            $this->assertTrue(
                $this->manager->valider($tache),
                "La priorité $niveau devrait être valide."
            );
        }
    }

    // ─── TEST 5 : Règle 4 — Catégorie agricole reconnue ──────────────────────

    /**
     * Une catégorie non reconnue doit être rejetée.
     */
    public function testCategorieInconnueRejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La catégorie "Jardinage" est invalide.');

        $tache = $this->creerTacheValide();
        $tache->setCategorie('Jardinage');

        $this->manager->valider($tache);
    }

    /**
     * Une catégorie vide doit être rejetée.
     */
    public function testCategorieVideRejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tache = $this->creerTacheValide();
        $tache->setCategorie('');

        $this->manager->valider($tache);
    }

    /**
     * Toutes les catégories agricoles définies sont valides.
     */
    public function testToutesLesCategoriesAgricolesValides(): void
    {
        foreach (Tache::CATEGORIES as $categorie) {
            $tache = $this->creerTacheValide();
            $tache->setCategorie($categorie);

            $this->assertTrue(
                $this->manager->valider($tache),
                "La catégorie '$categorie' devrait être valide."
            );
        }
    }

    // ─── TEST 6 : Règle 5 — Cohérence des dates ───────────────────────────────

    /**
     * Une date de fin antérieure à la date de début doit être rejetée.
     */
    public function testDateFinAvantDateDebutRejetee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être postérieure ou égale à la date de début.');

        $tache = $this->creerTacheValide();
        $tache->setDateDebut(new \DateTime('+3 days'))
              ->setDateFin(new \DateTime('today'));

        $this->manager->valider($tache);
    }

    /**
     * Date de début = Date de fin (même jour) doit être acceptée.
     */
    public function testDateDebutEgaleADateFinAcceptee(): void
    {
        $today = new \DateTime('today');
        $tache = $this->creerTacheValide();
        $tache->setDateDebut($today)->setDateFin($today);

        $this->assertTrue($this->manager->valider($tache));
    }

    /**
     * Si une seule date est définie, aucune règle de cohérence ne s'applique.
     */
    public function testUneSeuledateDefined(): void
    {
        $tache = $this->creerTacheValide();
        $tache->setDateDebut(new \DateTime('today'))->setDateFin(null);

        $this->assertTrue($this->manager->valider($tache));
    }

    // ─── TEST 7 : Affectation d'un employé ────────────────────────────────────

    /**
     * affecterEmploye() doit mettre à jour l'idEmploye de la tâche.
     */
    public function testAffecterEmployeValide(): void
    {
        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_EN_ATTENTE);

        $this->manager->affecterEmploye($tache, 42);

        $this->assertSame(42, $tache->getIdEmploye());
    }

    /**
     * Affecter un employé à une tâche "Terminée" doit lever une exception.
     */
    public function testAffecterEmployeSurTacheTermineeRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Impossible d\'affecter un employé à une tâche terminée, validée ou annulée.'
        );

        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_TERMINE);

        $this->manager->affecterEmploye($tache, 10);
    }

    /**
     * Affecter un employé à une tâche "Annulée" doit lever une exception.
     */
    public function testAffecterEmployeSurTacheAnnuleeRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_ANNULE);

        $this->manager->affecterEmploye($tache, 8);
    }

    // ─── TEST 8 : Cycle de vie des statuts ────────────────────────────────────

    /**
     * terminer() passe le statut d'"En cours" à "Terminé".
     */
    public function testTerminerTacheEnCours(): void
    {
        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_EN_COURS);

        $this->manager->terminer($tache);

        $this->assertSame(Tache::STATUT_TERMINE, $tache->getStatut());
    }

    /**
     * Tenter de terminer une tâche déjà "Terminée" lève une exception.
     */
    public function testTerminerTacheDejaTermineeRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Seule une tâche "En cours" peut être marquée comme terminée.');

        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_TERMINE);

        $this->manager->terminer($tache);
    }

    /**
     * Tenter de terminer une tâche "En attente" lève une exception.
     */
    public function testTerminerTacheEnAttenteRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_EN_ATTENTE);

        $this->manager->terminer($tache);
    }

    /**
     * validerTache() passe le statut de "Terminé" à "Validé".
     */
    public function testValiderTacheTerminee(): void
    {
        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_TERMINE);

        $this->manager->validerTache($tache);

        $this->assertSame(Tache::STATUT_VALIDE, $tache->getStatut());
    }

    /**
     * Tenter de valider une tâche "En cours" lève une exception.
     */
    public function testValiderTacheEnCoursRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Seule une tâche "Terminée" peut être validée.');

        $tache = $this->creerTacheValide();
        $tache->setStatut(Tache::STATUT_EN_COURS);

        $this->manager->validerTache($tache);
    }

    /**
     * Cycle complet : En attente → En cours → Terminé → Validé.
     * Représente le workflow d'une tâche de récolte agricole.
     */
    public function testCycleCompletRecolteOlives(): void
    {
        $tache = new Tache();
        $tache->setTitre('Récolte olives domaine nord')
              ->setPriorite(4)
              ->setCategorie('Récolte')
              ->setStatut(Tache::STATUT_EN_ATTENTE);

        // Étape 1 : affecter un ouvrier agricole
        $this->manager->affecterEmploye($tache, 15);
        $this->assertSame(15, $tache->getIdEmploye());

        // Étape 2 : démarrer la tâche
        $tache->setStatut(Tache::STATUT_EN_COURS);
        $this->assertSame(Tache::STATUT_EN_COURS, $tache->getStatut());

        // Étape 3 : terminer la récolte
        $this->manager->terminer($tache);
        $this->assertSame(Tache::STATUT_TERMINE, $tache->getStatut());

        // Étape 4 : validation par le chef d'exploitation
        $this->manager->validerTache($tache);
        $this->assertSame(Tache::STATUT_VALIDE, $tache->getStatut());
    }
}
