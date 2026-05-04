<?php

namespace App\Tests\Marketplace\Service;

use App\Service\Marketplace\ChatbotService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ChatbotService.
 *
 * Vérifie la logique de génération des réponses, les recommandations
 * de produits agricoles et l'affectation intelligente des demandes.
 */
class ChatbotServiceTest extends TestCase
{
    private ChatbotService $chatbotService;

    protected function setUp(): void
    {
        // Si le service a des dépendances (ex: HttpClientInterface, LoggerInterface), 
        // on les mocke ici. Pour ce test, on suppose une instanciation simple ou mockée.
        $this->chatbotService = new ChatbotService();
    }

    // ─── TEST 1 : Réponses du chatbot ─────────────────────────────────────────

    public function testGenererReponseSalutation(): void
    {
        $messageUtilisateur = "Bonjour, je cherche des engrais.";
        
        $reponse = $this->chatbotService->genererReponse($messageUtilisateur);

        $this->assertIsString($reponse);
        $this->assertStringContainsStringIgnoringCase('Bonjour', $reponse);
    }

    public function testGenererReponseProduitIntrouvable(): void
    {
        $messageUtilisateur = "Je veux acheter un produit_inconnu_xyz.";
        
        $reponse = $this->chatbotService->genererReponse($messageUtilisateur);

        $this->assertIsString($reponse);
        $this->assertStringContainsStringIgnoringCase('désolé', $reponse);
    }

    // ─── TEST 2 : Recommandations de produits ─────────────────────────────────

    public function testRecommanderProduitsPourTomates(): void
    {
        $contexte = "culture de tomates";
        
        $recommandations = $this->chatbotService->obtenirRecommandations($contexte);

        $this->assertIsArray($recommandations);
        $this->assertNotEmpty($recommandations);
        
        // Vérifie qu'on recommande au moins un engrais ou produit pertinent
        $trouveEngrais = false;
        foreach ($recommandations as $produit) {
            if (stripos($produit['nom'], 'engrais') !== false || stripos($produit['categorie'], 'engrais') !== false) {
                $trouveEngrais = true;
                break;
            }
        }
        
        $this->assertTrue($trouveEngrais, 'Le chatbot devrait recommander de l\'engrais pour les tomates.');
    }

    // ─── TEST 3 : Affectation intelligente ────────────────────────────────────

    public function testAffecterDemandeAssistanceTechnique(): void
    {
        $messageUtilisateur = "Mon tracteur est en panne, j'ai besoin d'aide pour réparer le moteur.";
        
        $departementAffecte = $this->chatbotService->affecterDemande($messageUtilisateur);

        $this->assertSame('Maintenance', $departementAffecte, 'Les problèmes de tracteur doivent aller à la Maintenance.');
    }

    public function testAffecterDemandeServiceCommercial(): void
    {
        $messageUtilisateur = "Quels sont les prix pour 100Kg de semences de blé ?";
        
        $departementAffecte = $this->chatbotService->affecterDemande($messageUtilisateur);

        $this->assertSame('Commercial', $departementAffecte, 'Les demandes de prix doivent aller au service Commercial.');
    }

    public function testAffecterDemandeParDefaut(): void
    {
        $messageUtilisateur = "Comment fonctionne votre site ?";
        
        $departementAffecte = $this->chatbotService->affecterDemande($messageUtilisateur);

        $this->assertSame('Support Général', $departementAffecte, 'Les questions vagues doivent aller au Support Général.');
    }
}
