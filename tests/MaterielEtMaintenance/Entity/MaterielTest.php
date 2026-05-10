<?php

namespace App\Tests\MaterielEtMaintenance\Entity;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Entity\MaterielEtMaintenance\Maintenance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitaires de l'entité Materiel.
 *
 * Vérifie les getters/setters, les valeurs par défaut, 
 * la logique de calcul de maintenance et les validations.
 */
class MaterielTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function creerMaterielValide(): Materiel
    {
        $materiel = new Materiel();
        $materiel->setNom('Tracteur John Deere')
                 ->setType('Tracteur')
                 ->setEtat('Bon')
                 ->setDateAchat(new \DateTime('-1 year'));

        return $materiel;
    }

    // ─── TEST 1 : Valeurs par défaut ──────────────────────────────────────────

    public function testValeursParDefaut(): void
    {
        $materiel = new Materiel();

        $this->assertSame(0, $materiel->getHeuresUtilisation());
        $this->assertSame(500, $materiel->getSeuilMaintenanceHeures());
        $this->assertSame(0, $materiel->getDerniereMaintenanceHeures());
        $this->assertSame('en_service', $materiel->getStatut());
        $this->assertCount(0, $materiel->getMaintenances());
        $this->assertCount(0, $materiel->getAlerteTechniciens());
    }

    // ─── TEST 2 : Initialisation des seuils par défaut ───────────────────────

    public function testInitialiserSeuilParDefautTracteur(): void
    {
        $materiel = new Materiel();
        $materiel->setType('Tracteur');
        $materiel->initialiserSeuilParDefaut();

        $this->assertSame(500, $materiel->getSeuilMaintenanceHeures());
    }

    public function testInitialiserSeuilParDefautMoissonneuse(): void
    {
        $materiel = new Materiel();
        $materiel->setType('Moissonneuse');
        $materiel->initialiserSeuilParDefaut();

        $this->assertSame(300, $materiel->getSeuilMaintenanceHeures());
    }

    public function testInitialiserSeuilParDefautAutre(): void
    {
        $materiel = new Materiel();
        $materiel->setType('Autre');
        $materiel->initialiserSeuilParDefaut();

        $this->assertSame(500, $materiel->getSeuilMaintenanceHeures());
    }

    // ─── TEST 3 : Validation des données ──────────────────────────────────────

    public function testMaterielValide(): void
    {
        $materiel = $this->creerMaterielValide();
        $erreurs = $this->validator->validate($materiel);

        $this->assertCount(0, $erreurs);
    }

    public function testNomEstObligatoire(): void
    {
        $materiel = $this->creerMaterielValide();
        $materiel->setNom('');

        $erreurs = $this->validator->validate($materiel);
        $this->assertGreaterThan(0, count($erreurs));
    }

    public function testDateAchatDansLeFuturRejetee(): void
    {
        $materiel = $this->creerMaterielValide();
        $materiel->setDateAchat(new \DateTime('+1 day')); // Date au futur

        $erreurs = $this->validator->validate($materiel);
        $this->assertGreaterThan(0, count($erreurs));
    }

    public function testTypeInvalideRejete(): void
    {
        $materiel = $this->creerMaterielValide();
        $materiel->setType('Avion'); // Ne fait pas partie des choix

        $erreurs = $this->validator->validate($materiel);
        $this->assertGreaterThan(0, count($erreurs));
    }

    public function testEtatInvalideRejete(): void
    {
        $materiel = $this->creerMaterielValide();
        $materiel->setEtat('Détruit'); // Ne fait pas partie des choix

        $erreurs = $this->validator->validate($materiel);
        $this->assertGreaterThan(0, count($erreurs));
    }

    // ─── TEST 4 : Calcul de la prochaine maintenance ──────────────────────────

    public function testCalculerProchaineMaintenanceAPartirDateAchat(): void
    {
        $materiel = new Materiel();
        $dateAchat = new \DateTime('2023-01-01');
        $materiel->setDateAchat($dateAchat);

        $materiel->calculerProchaineMaintenance();

        $dateAttendue = new \DateTime('2023-07-01');
        $this->assertEquals($dateAttendue->format('Y-m-d'), $materiel->getDateProchaineMaintenance()->format('Y-m-d'));
    }

    // ─── TEST 5 : Situation de la maintenance ─────────────────────────────────

    public function testGetSituationMaintenanceNonPlanifie(): void
    {
        $materiel = new Materiel();
        // Pas de date prochaine maintenance définie
        $this->assertSame('non_planifie', $materiel->getSituationMaintenance());
    }

    public function testGetSituationMaintenancePlanifieeLorsquInterventionEnCours(): void
    {
        $materiel = new Materiel();
        $maintenance = new Maintenance();
        $maintenance->setStatutMaintenance('planifiee');
        
        $materiel->addMaintenance($maintenance);

        $this->assertSame('planifiee', $materiel->getSituationMaintenance());
    }

    public function testGetSituationMaintenanceEnRetard(): void
    {
        $materiel = new Materiel();
        $materiel->setDateProchaineMaintenance(new \DateTime('-1 day')); // Hier

        $this->assertSame('en_retard', $materiel->getSituationMaintenance());
    }

    public function testGetSituationMaintenanceBientot(): void
    {
        $materiel = new Materiel();
        $materiel->setDateProchaineMaintenance(new \DateTime('+3 days')); // Dans 3 jours

        $this->assertSame('bientot', $materiel->getSituationMaintenance());
    }

    public function testGetSituationMaintenanceOk(): void
    {
        $materiel = new Materiel();
        $materiel->setDateProchaineMaintenance(new \DateTime('+30 days')); // Dans 1 mois

        $this->assertSame('ok', $materiel->getSituationMaintenance());
    }

    // ─── TEST 6 : Génération du token QR Code ─────────────────────────────────

    public function testGenerateTokenCreeUnTokenSiVide(): void
    {
        $materiel = new Materiel();
        $this->assertNull($materiel->getQrCodeToken());

        $materiel->generateToken();

        $this->assertNotNull($materiel->getQrCodeToken());
        $this->assertSame(32, strlen($materiel->getQrCodeToken())); // bin2hex(random_bytes(16)) fait 32 caractères
    }

    public function testGenerateTokenNeRemplacePasUnTokenExistant(): void
    {
        $materiel = new Materiel();
        $materiel->setQrCodeToken('TOKEN_EXISTANT');

        $materiel->generateToken();

        $this->assertSame('TOKEN_EXISTANT', $materiel->getQrCodeToken());
    }
}
