<?php

namespace App\Tests\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Service\MaterielEtMaintenance\GroqPredictionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GroqPredictionServiceTest extends TestCase
{
    private GroqPredictionService $groqPredictionService;
    private $httpClientMock;
    private $loggerMock;

    protected function setUp(): void
    {
        // 1. Création des Mocks (Faux objets pour ne pas vraiment appeler l'API Groq)
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        // 2. Initialisation du service avec les mocks
        $this->groqPredictionService = new GroqPredictionService(
            $this->httpClientMock,
            $this->loggerMock
        );
    }

    /**
     * TEST 1 : Cas nominal (L'API Groq répond correctement avec un bon JSON)
     */
    public function testGeneratePredictionSuccess(): void
    {
        // Préparation d'un faux matériel
        $materiel = new Materiel();
        $materiel->setNom('Tracteur John Deere');
        $materiel->setType('Tracteur');
        $materiel->setEtat('Bon');
        $materiel->setHeuresUtilisation(450);

        // Fausse réponse JSON que l'API est censée retourner
        $fakeJsonResponse = json_encode([
            'risque' => 'Faible',
            'score_risque' => 20,
            'analyse' => 'Tout va bien.',
            'conseils' => ['Faire le plein'],
            'prochaine_etape' => 'Continuer utilisation'
        ]);

        // Fausse structure de réponse complète de l'API Groq
        $fakeGroqResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => $fakeJsonResponse
                    ]
                ]
            ]
        ];

        // Création d'un mock pour la ResponseInterface
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($fakeGroqResponse);

        // On dit à notre faux HttpClient de retourner notre fausse réponse
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($responseMock);

        // Exécution de la méthode
        $result = $this->groqPredictionService->generatePrediction($materiel);

        // Vérifications (Assertions)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('risque', $result);
        $this->assertEquals('Faible', $result['risque']);
        $this->assertEquals(20, $result['score_risque']);
    }

    /**
     * TEST 2 : Cas d'erreur (L'API Groq est injoignable ou renvoie une erreur 500)
     */
    public function testGeneratePredictionApiError(): void
    {
        $materiel = new Materiel();

        // On simule une exception (Erreur réseau par exemple) lors de l'appel HTTP
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Erreur de connexion API'));

        // On vérifie que le Logger est bien appelé pour enregistrer l'erreur
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Groq Prediction Error: Erreur de connexion API'));

        // Exécution de la méthode
        $result = $this->groqPredictionService->generatePrediction($materiel);

        // L'application ne doit pas crasher, elle doit renvoyer un tableau d'erreur géré
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Impossible de générer la prédiction', $result['message']);
    }

    /**
     * TEST 3 : Cas d'erreur (L'API Groq renvoie une réponse vide ou mal formatée)
     */
    public function testGeneratePredictionEmptyResponse(): void
    {
        $materiel = new Materiel();

        // Fausse réponse Groq sans le champ 'content'
        $fakeGroqResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => null // Contenu vide !
                    ]
                ]
            ]
        ];

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('toArray')->willReturn($fakeGroqResponse);

        $this->httpClientMock->method('request')->willReturn($responseMock);

        // Exécution de la méthode
        $result = $this->groqPredictionService->generatePrediction($materiel);

        // Vérifications
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Réponse vide de Groq', $result['message']);
    }
}
