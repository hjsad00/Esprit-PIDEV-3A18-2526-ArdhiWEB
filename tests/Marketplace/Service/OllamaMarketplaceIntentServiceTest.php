<?php

namespace App\Tests\Marketplace\Service;

use App\Service\Marketplace\OllamaMarketplaceIntentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests des intentions du Chatbot Marketplace (Ajout et Vidage de Panier).
 */
class OllamaMarketplaceIntentServiceTest extends TestCase
{
    private function createServiceWithMockedResponse(array $jsonResponse): OllamaMarketplaceIntentService
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn([
            'response' => json_encode($jsonResponse)
        ]);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $mockLogger = $this->createMock(LoggerInterface::class);

        return new OllamaMarketplaceIntentService($mockHttpClient, $mockLogger);
    }

    public function testChatbotIntentionAjoutPanier(): void
    {
        // On simule qu'Ollama a détecté un ajout au panier (intention "achat")
        $service = $this->createServiceWithMockedResponse([
            'intention' => 'achat',
            'produits' => [
                ['nom' => 'Tomates', 'quantite' => 3],
                ['nom' => 'Pommes', 'quantite' => 5]
            ],
            'critere' => null,
            'recherche' => null,
            'categorie' => null,
            'prixMin' => null,
            'prixMax' => null,
        ]);

        $result = $service->analyser('Je veux ajouter 3 tomates et 5 pommes dans mon panier');

        $this->assertEquals('achat', $result['intention'], 'L\'intention doit être "achat" pour un ajout au panier.');
        
        $this->assertCount(2, $result['produits'], 'Il doit y avoir 2 produits à ajouter au panier.');
        $this->assertEquals('Tomates', $result['produits'][0]['nom']);
        $this->assertEquals(3, $result['produits'][0]['quantite']);
        $this->assertEquals('Pommes', $result['produits'][1]['nom']);
        $this->assertEquals(5, $result['produits'][1]['quantite']);
    }

    public function testChatbotIntentionViderPanier(): void
    {
        // On simule qu'Ollama a détecté une demande de vidage de panier (intention "vider_panier")
        $service = $this->createServiceWithMockedResponse([
            'intention' => 'vider_panier',
            'produits' => [],
            'critere' => null,
            'recherche' => null,
            'categorie' => null,
            'prixMin' => null,
            'prixMax' => null,
        ]);

        $result = $service->analyser('Vider mon panier svp');

        $this->assertEquals('vider_panier', $result['intention'], 'L\'intention doit bien être reconnue comme "vider_panier".');
        $this->assertCount(0, $result['produits'], 'Il ne doit y avoir aucun produit retourné pour le vidage.');
    }

    public function testChatbotIntentionAjoutPanierHeuristiqueSecours(): void
    {
        // Si l'API Ollama échoue ou renvoie hors sujet mais que le texte correspond à un ajout :
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willThrowException(new \RuntimeException('Erreur API'));
        $mockLogger = $this->createMock(LoggerInterface::class);
        
        $service = new OllamaMarketplaceIntentService($mockHttpClient, $mockLogger);

        $result = $service->analyser('Ajouter 10 Tracteurs au panier');

        // La heuristique de secours devrait corriger le tir toute seule à la volée !
        $this->assertEquals('achat', $result['intention']);
        $this->assertNotEmpty($result['produits']);
        $this->assertEquals(10, $result['produits'][0]['quantite']);
    }

    public function testChatbotIntentionViderPanierHeuristiqueSecours(): void
    {
        // Si l'API Ollama échoue, vérifions que le mot clef vider panier déclenche la heuristique
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willThrowException(new \RuntimeException('Erreur API'));
        $mockLogger = $this->createMock(LoggerInterface::class);
        
        $service = new OllamaMarketplaceIntentService($mockHttpClient, $mockLogger);

        $result = $service->analyser('je veux vider le panier');

        $this->assertEquals('vider_panier', $result['intention']);
    }
}
