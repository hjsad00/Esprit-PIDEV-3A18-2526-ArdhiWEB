<?php

namespace App\Service\Marketplace;

class ChatbotService
{
    public function genererReponse(string $message): string
    {
        $messageLower = strtolower($message);
        
        if (str_contains($messageLower, 'bonjour') || str_contains($messageLower, 'salut')) {
            return "Bonjour ! Comment puis-je vous aider aujourd'hui ?";
        }
        
        if (str_contains($messageLower, 'produit_inconnu')) {
            return "Je suis désolé, je n'ai pas pu trouver ce produit dans notre catalogue.";
        }
        
        return "Je ne suis pas sûr de comprendre. Pouvez-vous reformuler ?";
    }

    public function obtenirRecommandations(string $contexte): array
    {
        $contexteLower = strtolower($contexte);
        $recommandations = [];
        
        if (str_contains($contexteLower, 'tomates') || str_contains($contexteLower, 'culture')) {
            $recommandations[] = [
                'nom' => 'Engrais spécial tomates',
                'categorie' => 'Engrais'
            ];
            $recommandations[] = [
                'nom' => 'Tuteurs en bambou',
                'categorie' => 'Accessoires'
            ];
        }
        
        return $recommandations;
    }

    public function affecterDemande(string $message): string
    {
        $messageLower = strtolower($message);
        
        if (str_contains($messageLower, 'tracteur') || str_contains($messageLower, 'panne') || str_contains($messageLower, 'réparer')) {
            return 'Maintenance';
        }
        
        if (str_contains($messageLower, 'prix') || str_contains($messageLower, 'acheter') || str_contains($messageLower, 'devis')) {
            return 'Commercial';
        }
        
        return 'Support Général';
    }
}
