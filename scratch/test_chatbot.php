<?php
require __DIR__.'/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
$chatbotService = $container->get('App\Service\EmployeTache\ChatbotService');

// Test 1: Message connu (déterministe)
echo "---------------------------------\n";
echo "Test 1: Message connu\n";
echo "---------------------------------\n";
$res1 = $chatbotService->traiterMessage("qui sont les 3 meilleurs", 1);
echo "Intention: " . $res1->intention . "\n";
echo "Réponse:\n" . $res1->reponse . "\n\n";

// Test 2: Message inconnu (Fallback Gemini)
echo "---------------------------------\n";
echo "Test 2: Message fallback Gemini\n";
echo "---------------------------------\n";
$res2 = $chatbotService->traiterMessage("J'ai besoin d'aide pour organiser une réunion", 1);
echo "Intention: " . $res2->intention . "\n";
echo "Réponse:\n" . $res2->reponse . "\n";

