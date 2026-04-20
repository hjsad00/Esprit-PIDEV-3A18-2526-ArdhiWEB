<?php

namespace App\Model\Meteo;

class Recommandation
{
    public const NIVEAU_POSITIVE = 'POSITIVE';
    public const NIVEAU_WARNING  = 'WARNING';
    public const NIVEAU_DANGER   = 'DANGER';
 
    // Propriétés publiques (conservées pour compatibilité)
    public string $niveau;
    public string $message;
    public string $notifType;
 
    public function __construct(string $niveau, string $message, string $notifType)
    {
        $this->niveau    = $niveau;
        $this->message   = $message;
        $this->notifType = $notifType;
    }
   
    public function getNotifType(): string
    {
        return $this->notifType;
    }
 
    /** ✅ FIX BUG 2 — appelé par NotificationService ligne 128 */
    public function getNiveau(): string
    {
        return $this->niveau;
    }
 
    /** Déjà présent, inchangé */
    public function getMessage(): string
    {
        return $this->message;
    }
 
    public function isPositive(): bool
    {
        return $this->niveau === self::NIVEAU_POSITIVE;
    }
 
    public function isDanger(): bool
    {
        return $this->niveau === self::NIVEAU_DANGER;
    }
 
    public function isWarning(): bool
    {
        return $this->niveau === self::NIVEAU_WARNING;
    }
}
