<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class ChatbotService
{
    private const API_KEY = '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';
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL_NAME = 'llama-3.3-70b-versatile';
    private const MAX_MESSAGES = 20;

    private HttpClientInterface $client;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, RequestStack $requestStack, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Sends a message to the AI and gets a response, maintaining conversation history.
     */
    public function chat(string $sessionId, string $systemContext, string $userMessage): string
    {
        try {
            $session = $this->requestStack->getSession();
            $historyKey = 'chat_history_' . $sessionId;
            $history = $session->get($historyKey, []);

            // 1. Prepare Messages
            $messages = [
                ['role' => 'system', 'content' => $systemContext]
            ];

            // Add previous history
            foreach ($history as $msg) {
                $messages[] = $msg;
            }

            // Add current user message
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $jsonPayload = [
                'model' => self::MODEL_NAME,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ];

            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::API_KEY,
                    'Content-Type' => 'application/json',
                ],
                'json' => $jsonPayload,
                'timeout' => 60,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception("API Error: " . $response->getContent(false));
            }

            $content = $response->toArray();
            $aiResponse = $content['choices'][0]['message']['content'] ?? 'Désolé, je ne peux pas répondre pour le moment.';

            // 2. Update History
            $history[] = ['role' => 'user', 'content' => $userMessage];
            $history[] = ['role' => 'assistant', 'content' => $aiResponse];

            // Limit history size
            if (count($history) > self::MAX_MESSAGES) {
                $history = array_slice($history, -self::MAX_MESSAGES);
            }

            $session->set($historyKey, $history);

            return $aiResponse;

        } catch (\Exception $e) {
            $this->logger->error("ChatbotService error: " . $e->getMessage());
            return "Désolé, je rencontre une erreur de connexion à mon intelligence. (" . $e->getMessage() . ")";
        }
    }

    /**
     * Clears history for a session.
     */
    public function clearHistory(string $sessionId): void
    {
        $this->requestStack->getSession()->remove('chat_history_' . $sessionId);
    }

    /**
     * Gets history for a session.
     */
    public function getHistory(string $sessionId): array
    {
        return $this->requestStack->getSession()->get('chat_history_' . $sessionId, []);
    }
}
