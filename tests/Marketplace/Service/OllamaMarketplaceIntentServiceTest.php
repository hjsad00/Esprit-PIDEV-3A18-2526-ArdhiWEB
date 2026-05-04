<?php

namespace App\Tests\Marketplace\Service;

use App\Service\Marketplace\OllamaMarketplaceIntentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires du service Chatbot Ollama pour Marketplace.
 * 
 * Ce test vérifie le comportement de l'analyseur d'intentions sans faire de 
 * requêtes HTTP réelles (en "mockant" le HttpClient) pour la recherche et 
 * l'ajout au panier.
 */
class OllamaMarketplaceIntentServiceTest extends TestCase
{
    public function testChatbotIntentionAchatRemplirPanier(): void
    {
        // 1. On "mock" la réponse de l'API Ollama (on simule qu'Ollama retourne ce JSON)
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        
        $ollamaAnswer = json_encode([
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
        
        $mockResponse->method('toArray')->willReturn([
            'response' => $ollamaAnswer
        ]);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $mockLogger = $this->createMock(LoggerInterface::class);

        // 2. On instancie le service avec nos Mocks
        $service = new OllamaMarketplaceIntentService($mockHttpClient, $mockLogger);

        // 3. Appel de la méthode à tester (le message n'a pas d'importance ici, c'est le mock qui répond)
        $result = $service->analyser('Je veux 3 Tomates et 5 Pommes');

        // 4. Assertions : Vérifier que le chatbot a bien compris l'intention de remplir le panier
        $this->assertEquals('achat', $result['intention']);
        
        // Vérification des produits extraits
        $this->assertCount(2, $result['produits']);
        $this->assertEquals('Tomates', $result['produits'][0]['nom']);
        $this->assertEquals(3, $result['produits'][0]['quantite']);
        $this->assertEquals('Pommes', $result['produits'][1]['nom']);
        $this->assertEquals(5, $result['produits'][1]['quantite']);
    }

    public function testChatbotIntentionRechercheProduit()
    {
        // 1. Simulation d'une recherche avec des filtres
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        
        $ollamaAnswer = json_encode([
            'intention' => 'filtrer',
            'produits' => [],
            'critere' => 'prix_asc',
            'recherche' => 'Tracteur',
            'categorie' => 'Agricole',
            'prixMin' => 100,
            'prixMax' => 5000,
        ]);
        
        $mockResponse->method('toArray')->willReturn([
            'response' => $ollamaAnswer
        ]);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $mockLogger = $this->createMock(LoggerInterface::class);

        $service = new OllamaMarketplaceIntentService($mockHttpClient, $mockLogger);

        // 3. Action
        $result = $service->analyser('Cherche des tracteurs agricoles entre 100 et 5000 dinars le moins cher possible');

        // 4. Assertions
        $this->assertEquals('filtrer', $result['intention']);
        $this->assertEquals('Tracteur', $result['recherche']);
        $this->assertEquals('Agricole', $result['categorie']);
        $this->assertEquals(100, $result['prixMin']);
        $this->assertEquals(5000, $result['prixMax']);
        $this->assertEquals('prix_asc', $result['critere']);
    }

    public function testChatbotMessageVide()
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $service = new OllamaMarketplaceIntentService($mockHttpClient, $mockLogger);

        // Action sur un message vide (ne devrait pas appeler Ollama, devrait retourner hors_sujet par défaut)
        $result = $service->analyser('   ');

        // Assertions du fallback (normalement le service gère cela tout seul)
        $this->assertEquals('hors_sujet', $result['intention']);
    }
}
