<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$helper = $container->get('symfonycasts.reset_password.helper');
$em = $container->get('doctrine.orm.entity_manager');
$userRepo = $container->get('App\Repository\UserAndDiag\UserRepository');

// Find any user
$user = $userRepo->findOneBy(['email' => 'hajsalemadel10@gmail.com']);
if (!$user) {
    echo "User not found\n";
    exit;
}

$out = "";
try {
    // 1. Generate token
    $out .= "Generating token...\n";
    $token = $helper->generateResetToken($user);
    $publicToken = $token->getToken();
    $out .= "Public token: " . $publicToken . "\n";

    // We must manually persist because the controller isn't running
    // Wait, generateResetToken ALREADY calls repository->persistResetPasswordRequest!!

    // 2. Validate token
    $out .= "Validating token...\n";
    $fetchedUser = $helper->validateTokenAndFetchUser($publicToken);
    $out .= "Success! Fetched user: " . $fetchedUser->getEmail() . "\n";

} catch (\Exception $e) {
    $out .= "EXCEPTION: " . get_class($e) . "\n";
    $out .= "MESSAGE: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getReason')) {
        $out .= "REASON: " . $e->getReason() . "\n";
    }
}
file_put_contents('dump_tokens.txt', $out);
