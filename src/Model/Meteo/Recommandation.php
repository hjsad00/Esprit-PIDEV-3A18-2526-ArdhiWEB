<?php

namespace App\Model\Meteo;

class Recommandation
{
    public const NIVEAU_POSITIVE = 'POSITIVE';
    public const NIVEAU_WARNING = 'WARNING';
    public const NIVEAU_DANGER = 'DANGER';

    public string $niveau;
    public string $message;
    public string $notifType;

    public function __construct(string $niveau, string $message, string $notifType)
    {
        $this->niveau = $niveau;
        $this->message = $message;
        $this->notifType = $notifType;
    }

    public function isPositive(): bool
    {
        return $this->niveau === self::NIVEAU_POSITIVE;
    }
}
