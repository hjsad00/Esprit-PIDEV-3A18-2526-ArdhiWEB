<?php

namespace App\Tests\Employe\Entity;

use App\Entity\EmployeTache\Tache;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Tache.
 *
 * Vérifie les constantes, getters/setters, les valeurs par défaut
 * et les méthodes utilitaires (retard, priorité, statut…).
 *
 * Contexte agricole : irrigation, récolte, fertilisation, plantation, etc.
 *
 * Exécution : php bin/phpunit tests/Employe/Entity/TacheTest.php
 */
class TacheTest extends TestCase
{
    // ─── Helper ───────────────────────────────────────────────────────────────

    private function creerTacheValide(): Tache
    {
        $tache = new Tache();
        $tache->setTitre('Irrigation parcelle blé nord')
              ->setDescription('Arroser la parcelle blé nord, débit 3L/min, durée 2h.')
              ->setStatut(Tache::STATUT_EN_COURS)
              ->setPriorite(3)
              ->setCategorie('Irrigation')
              ->setDateDebut(new \DateTime('today'))
              ->setDateFin(new \DateTime('+3 days'))
              ->setIdAgriculteur(7)
              ->setIdEmploye(12);

        return $tache;
    }

    // ─── TEST 1 : Constantes métier ────────────────────────────────────────────

    /**
     * Les constantes de statut doivent avoir les valeurs françaises attendues.
     */
    public function testConstantesStatut(): void
    {
        $this->assertSame('En attente', Tache::STATUT_EN_ATTENTE);
        $this->assertSame('En cours',   Tache::STATUT_EN_COURS);
        $this->assertSame('Terminé',    Tache::STATUT_TERMINE);
        $this->assertSame('Validé',     Tache::STATUT_VALIDE);
        $this->assertSame('Annulé',     Tache::STATUT_ANNULE);
    }

    /**
     * Le tableau STATUTS doit contenir exactement les 5 statuts du cycle de vie.
     */
    public function testTableauStatutsComplet(): void
    {
        $this->assertCount(5, Tache::STATUTS);
        $this->assertContains(Tache::STATUT_EN_ATTENTE, Tache::STATUTS);
        $this->assertContains(Tache::STATUT_VALIDE,     Tache::STATUTS);
    }

    /**
     * Le tableau PRIORITES doit avoir 4 niveaux (Basse à Critique).
     */
    public function testTableauPriorites(): void
    {
        $this->assertCount(4, Tache::PRIORITES);
        $this->assertSame('Basse',    Tache::PRIORITES[1]);
        $this->assertSame('Critique', Tache::PRIORITES[4]);
    }

    /**
     * Les catégories agricoles doivent inclure Plantation et Récolte.
     */
    public function testCategoriesAgricoles(): void
    {
        $this->assertContains('Plantation',   Tache::CATEGORIES);
        $this->assertContains('Récolte',      Tache::CATEGORIES);
        $this->assertContains('Irrigation',   Tache::CATEGORIES);
        $this->assertContains('Fertilisation',Tache::CATEGORIES);
    }

    // ─── TEST 2 : Valeurs par défaut ──────────────────────────────────────────

    /**
     * Une nouvelle tâche doit être en statut "En attente" par défaut.
     */
    public function testStatutParDefaut(): void
    {
        $tache = new Tache();

        $this->assertSame(Tache::STATUT_EN_ATTENTE, $tache->getStatut());
    }

    /**
     * La priorité par défaut est 2 (Moyenne).
     */
    public function testPrioriteParDefaut(): void
    {
        $tache = new Tache();

        $this->assertSame(2, $tache->getPriorite());
    }

    /**
     * La catégorie par défaut est Plantation.
     */
    public function testCategorieParDefaut(): void
    {
        $tache = new Tache();

        $this->assertSame('Plantation', $tache->getCategorie());
    }

    /**
     * Le type de tâche par défaut est AUTRE.
     */
    public function testTypeTacheParDefaut(): void
    {
        $tache = new Tache();

        $this->assertSame('AUTRE', $tache->getTypeTache());
    }

    // ─── TEST 3 : Getters / Setters ───────────────────────────────────────────

    /**
     * Tous les champs textuels doivent être correctement stockés.
     */
    public function testGettersSettersBase(): void
    {
        $tache = $this->creerTacheValide();

        $this->assertSame('Irrigation parcelle blé nord', $tache->getTitre());
        $this->assertSame('Irrigation', $tache->getCategorie());
        $this->assertSame(Tache::STATUT_EN_COURS, $tache->getStatut());
        $this->assertSame(3, $tache->getPriorite());
        $this->assertSame(7, $tache->getIdAgriculteur());
        $this->assertSame(12, $tache->getIdEmploye());
    }

    /**
     * setDescription(null) doit être accepté (description facultative).
     */
    public function testDescriptionNullable(): void
    {
        $tache = new Tache();
        $tache->setDescription(null);

        $this->assertNull($tache->getDescription());
    }

    /**
     * setIdEmploye(null) représente une tâche non affectée.
     */
    public function testIdEmployeNullable(): void
    {
        $tache = new Tache();
        $tache->setIdEmploye(null);

        $this->assertNull($tache->getIdEmploye());
    }

    /**
     * setGoogleEventId et getGoogleEventId doivent fonctionner correctement.
     */
    public function testGoogleEventId(): void
    {
        $tache = new Tache();
        $tache->setGoogleEventId('gcal_event_abc123');

        $this->assertSame('gcal_event_abc123', $tache->getGoogleEventId());
    }

    /**
     * setTypeTache doit persister la valeur (ex: tâche urgente de récolte).
     */
    public function testSetTypeTache(): void
    {
        $tache = new Tache();
        $tache->setTypeTache('RECOLTE');

        $this->assertSame('RECOLTE', $tache->getTypeTache());
    }

    // ─── TEST 4 : Méthodes utilitaires ────────────────────────────────────────

    /**
     * getPrioriteLabel() doit retourner le libellé correspondant à la priorité.
     */
    public function testGetPrioriteLabel(): void
    {
        $tache = new Tache();

        $tache->setPriorite(1);
        $this->assertSame('Basse', $tache->getPrioriteLabel());

        $tache->setPriorite(4);
        $this->assertSame('Critique', $tache->getPrioriteLabel());
    }

    /**
     * getPrioriteCouleur() doit retourner la couleur CSS correcte.
     */
    public function testGetPrioriteCouleur(): void
    {
        $tache = new Tache();

        $tache->setPriorite(1);
        $this->assertSame('#3498db', $tache->getPrioriteCouleur()); // Basse - bleu

        $tache->setPriorite(4);
        $this->assertSame('#8e44ad', $tache->getPrioriteCouleur()); // Critique - violet
    }

    /**
     * getStatutIcone() doit retourner l'icône correspondant à chaque statut.
     */
    public function testGetStatutIcone(): void
    {
        $tache = new Tache();

        $tache->setStatut(Tache::STATUT_EN_ATTENTE);
        $this->assertSame('⏳', $tache->getStatutIcone());

        $tache->setStatut(Tache::STATUT_TERMINE);
        $this->assertSame('✅', $tache->getStatutIcone());

        $tache->setStatut(Tache::STATUT_ANNULE);
        $this->assertSame('❌', $tache->getStatutIcone());
    }

    /**
     * getStatutCouleur() doit retourner la couleur CSS correcte.
     */
    public function testGetStatutCouleur(): void
    {
        $tache = new Tache();

        $tache->setStatut(Tache::STATUT_EN_ATTENTE);
        $this->assertSame('#f39c12', $tache->getStatutCouleur());

        $tache->setStatut(Tache::STATUT_VALIDE);
        $this->assertSame('#1abc9c', $tache->getStatutCouleur());
    }

    // ─── TEST 5 : Détection du retard ─────────────────────────────────────────

    /**
     * Une tâche dont la date de fin est dépassée et le statut est "En cours"
     * doit être considérée en retard.
     */
    public function testIsEnRetardVrai(): void
    {
        $tache = new Tache();
        $tache->setStatut(Tache::STATUT_EN_COURS)
              ->setDateFin(new \DateTime('-1 day'));

        $this->assertTrue($tache->isEnRetard(), 'La tâche avec date dépassée doit être en retard.');
    }

    /**
     * Une tâche "Terminée" ne doit jamais être considérée en retard,
     * même si sa date de fin est passée.
     */
    public function testIsEnRetardFauxSiTermine(): void
    {
        $tache = new Tache();
        $tache->setStatut(Tache::STATUT_TERMINE)
              ->setDateFin(new \DateTime('-5 days'));

        $this->assertFalse($tache->isEnRetard(), 'Une tâche terminée n\'est pas en retard.');
    }

    /**
     * Une tâche "Validée" ne doit pas être en retard.
     */
    public function testIsEnRetardFauxSiValide(): void
    {
        $tache = new Tache();
        $tache->setStatut(Tache::STATUT_VALIDE)
              ->setDateFin(new \DateTime('-2 days'));

        $this->assertFalse($tache->isEnRetard());
    }

    /**
     * Une tâche sans date de fin ne peut pas être en retard.
     */
    public function testIsEnRetardFauxSansDateFin(): void
    {
        $tache = new Tache();
        $tache->setStatut(Tache::STATUT_EN_COURS)
              ->setDateFin(null);

        $this->assertFalse($tache->isEnRetard());
    }

    /**
     * Une tâche dont la date de fin est dans le futur n'est pas en retard.
     */
    public function testIsEnRetardFauxDateFuture(): void
    {
        $tache = new Tache();
        $tache->setStatut(Tache::STATUT_EN_COURS)
              ->setDateFin(new \DateTime('+7 days'));

        $this->assertFalse($tache->isEnRetard(), 'Date de fin dans le futur → pas en retard.');
    }
}
