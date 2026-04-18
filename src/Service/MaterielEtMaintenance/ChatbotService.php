<?php

namespace App\Service\MaterielEtMaintenance;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatbotService
{
    private HttpClientInterface $httpClient;
    private string $groqApiKey;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        // La clé fournie par l'utilisateur
        $this->groqApiKey = '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';
    }

    public function getResponse(array $messages, string $context): string
    {
        $systemPrompt = [
            'role' => 'system',
            'content' => "Tu es l'agent IA d'Ardhi, un assistant expert en gestion de matériel agricole et maintenance. 
            Ton nom est 'Ardhi Bot'. Tu es poli, professionnel et tu aides les agriculteurs à gérer leur parc machine.
            
            Règles métier Ardhi :
            - Le seuil de maintenance par défaut est de 500h (variable selon le type de machine).
            - La progression des heures est RELATIVE (ex: 50h/500h) et repart à 0 après chaque maintenance.
            - Les maintenances sont calculées automatiquement tous les 6 mois.
            - L'agriculteur peut signaler une panne urgente via le bouton rouge.
            
            Voici les données actuelles du parc matériel de l'utilisateur :
            $context
            
            Réponds de manière concise et utilise des emojis liés à l'agriculture 🚜🌱. Si l'utilisateur pose une question hors sujet, rappelle-lui poliment que tu es là pour l'aider sur son matériel."
        ];

        // Insérer le system prompt au début
        array_unshift($messages, $systemPrompt);

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 1024,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? "Désolé, je rencontre une petite difficulté technique 🚜. Réessayez dans un instant !";
        } catch (\Exception $e) {
            // Log local ou retour plus précis pour le dev en cas de 400
            $errorDetail = $e->getMessage();
            return "Désolé, une erreur technique est survenue lors de la communication avec mon cerveau IA 🤖. (Détail: $errorDetail)";
        }
    }
}
