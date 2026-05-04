<?php

namespace App\Tests\Marketplace\Service;

use App\Service\Marketplace\OllamaMarketplaceIntentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour OllamaMarketplaceIntentService.
 * Teste l'analyse des messages utilisateur et la détection d'intention.
 */
class MarketplaceIntentServiceTest extends TestCase
{
    private OllamaMarketplaceIntentService $service;
    private HttpClientInterface $httpClientMock;
    private LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->service = new OllamaMarketplaceIntentService(
            $this->httpClientMock,
            $this->loggerMock
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣ Test ANALYSER MESSAGE ACHAT (Purchase)
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageAchat(): void
    {
        $userMessage = 'Je veux acheter 3 kg de tomates';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'achat',
                'produits' => [['nom' => 'tomates', 'quantite' => 3]],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('achat', $intent['intention']);
        $this->assertNotEmpty($intent['produits']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 2️⃣ Test ANALYSER MESSAGE DISPONIBILITE
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageDisponibilite(): void
    {
        $userMessage = 'Est-ce que les oignons sont disponibles?';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'disponibilite',
                'produits' => [['nom' => 'oignons', 'quantite' => 1]],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('disponibilite', $intent['intention']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 3️⃣ Test ANALYSER MESSAGE VIDER PANIER
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageViderPanier(): void
    {
        $userMessage = 'Vide mon panier';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'vider_panier',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('vider_panier', $intent['intention']);
        $this->assertEmpty($intent['produits']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 4️⃣ Test ANALYSER MESSAGE SUPPRIMER PRODUIT DU PANIER
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageSupprimerProduit(): void
    {
        $userMessage = 'Retire les tomates de mon panier';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'supprimer_produit',
                'produits' => [['nom' => 'tomates', 'quantite' => 1]],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('supprimer_produit', $intent['intention']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 5️⃣ Test ANALYSER MESSAGE FILTRER PRODUITS
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageFiltrer(): void
    {
        $userMessage = 'Montre-moi les légumes les moins chers';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'filtrer',
                'produits' => [],
                'critere' => 'prix_asc',
                'recherche' => null,
                'categorie' => 'Legumes',
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('filtrer', $intent['intention']);
        $this->assertEquals('prix_asc', $intent['critere']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 6️⃣ Test ANALYSER MESSAGE SALUTATION
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageSalutation(): void
    {
        $userMessage = 'Bonjour! Comment ça va?';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'salutation',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('salutation', $intent['intention']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 7️⃣ Test ANALYSER MESSAGE REMERCIEMENT
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageRemerciement(): void
    {
        $userMessage = 'Merci beaucoup pour votre aide!';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'remerciement',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('remerciement', $intent['intention']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 8️⃣ Test ANALYSER MESSAGE HORS SUJET
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageHorsSujet(): void
    {
        $userMessage = 'Quel est le meilleur restaurant à Tunis?';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'hors_sujet',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('hors_sujet', $intent['intention']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 9️⃣ Test ANALYSER MESSAGE VIDE
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageVide(): void
    {
        $userMessage = '';

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('hors_sujet', $intent['intention']);
        $this->assertEmpty($intent['produits']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 🔟 Test ANALYSER MESSAGE AVEC PRIX RANGE
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageAvecPrixRange(): void
    {
        $userMessage = 'Montre-moi les fruits entre 5 et 20 dinars';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'filtrer',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => 'Fruits',
                'prixMin' => 5.0,
                'prixMax' => 20.0,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('filtrer', $intent['intention']);
        $this->assertEquals(5.0, $intent['prixMin']);
        $this->assertEquals(20.0, $intent['prixMax']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣1️⃣ Test ANALYSER MESSAGE AVEC CRITERE DE TRI
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageAvecCritereTri(): void
    {
        $userMessage = 'Je veux acheter des tomates triées par meilleur avis';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'achat',
                'produits' => [['nom' => 'tomates', 'quantite' => 1]],
                'critere' => 'avis_desc',
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('achat', $intent['intention']);
        $this->assertEquals('avis_desc', $intent['critere']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣2️⃣ Test ANALYSER MESSAGE AVEC CATEGORIES
    // ─────────────────────────────────────────────────────────────────────────────

    public function testAnalyserMessageAvecCategories(): void
    {
        $userMessage = 'Quels sont les cereales disponibles?';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'filtrer',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => 'Cereales',
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        $this->assertEquals('filtrer', $intent['intention']);
        $this->assertEquals('Cereales', $intent['categorie']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣3️⃣ Test ERROR HANDLING - HTTP Error
    // ─────────────────────────────────────────────────────────────────────────────

    public function testErrorHandlingHttpError(): void
    {
        $userMessage = 'Je veux acheter des tomates';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        // Should fallback to heuristic intent
        $this->assertNotNull($intent['intention']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣4️⃣ Test ERROR HANDLING - Invalid JSON Response
    // ─────────────────────────────────────────────────────────────────────────────

    public function testErrorHandlingInvalidJsonResponse(): void
    {
        $userMessage = 'Je veux acheter des tomates';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => 'invalid json {{{',
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        // Should fallback to heuristic intent
        $this->assertNotNull($intent['intention']);
        $this->assertIsArray($intent);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 1️⃣5️⃣ Test RESPONSE STRUCTURE
    // ─────────────────────────────────────────────────────────────────────────────

    public function testResponseStructure(): void
    {
        $userMessage = 'Bonjour';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'response' => json_encode([
                'intention' => 'salutation',
                'produits' => [],
                'critere' => null,
                'recherche' => null,
                'categorie' => null,
                'prixMin' => null,
                'prixMax' => null,
            ]),
        ]);

        $this->httpClientMock->method('request')->willReturn($response);

        $intent = $this->service->analyser($userMessage);

        // Verify response structure
        $this->assertArrayHasKey('intention', $intent);
        $this->assertArrayHasKey('produits', $intent);
        $this->assertArrayHasKey('critere', $intent);
        $this->assertArrayHasKey('recherche', $intent);
        $this->assertArrayHasKey('categorie', $intent);
        $this->assertArrayHasKey('prixMin', $intent);
        $this->assertArrayHasKey('prixMax', $intent);

        $this->assertIsString($intent['intention']);
        $this->assertIsArray($intent['produits']);
    }
}
