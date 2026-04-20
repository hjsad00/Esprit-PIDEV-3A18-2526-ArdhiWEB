<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Tache;
use App\Model\Meteo\Recommandation;
use App\Model\Meteo\WeatherData;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * ✅ MeteoService v2
 *
 * BUG 6 CORRIGÉ : cache 'meteo_current_data' était partagé entre toutes les locales.
 * Premier appel en FR mettait en cache la description en français.
 * Appels EN/AR recevaient la description en FR.
 *
 * FIX : clé de cache inclut la locale → 'meteo_current_fr', 'meteo_current_en', etc.
 *
 * AJOUT : getTypeTache() manquant dans Tache → utilisation défensive de getCategorie()
 */
class MeteoService
{
    private const CITY     = 'Tunis,TN';
    private const BASE_URL = 'https://api.openweathermap.org/data/2.5/weather';

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface      $cache,
        private TranslatorInterface $translator,
        private RequestStack        $requestStack,
        #[Autowire(env: 'OPENWEATHER_API_KEY')]
        private string $apiKey
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  MÉTÉO ACTUELLE
    // ══════════════════════════════════════════════════════════════════

    public function getCurrentWeather(): WeatherData
    {
        $locale   = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        // ✅ FIX BUG 6 : clé de cache par locale
        $cacheKey = 'meteo_current_' . $locale;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($locale) {
            $item->expiresAfter(300); // Cache 5 minutes

            $data = new WeatherData();
            try {
                $response = $this->client->request('GET', self::BASE_URL, [
                    'query' => [
                        'q'     => self::CITY,
                        'units' => 'metric',
                        'lang'  => $locale,
                        'appid' => $this->apiKey,
                    ],
                    'timeout' => 5,
                ]);

                if ($response->getStatusCode() === 200) {
                    $json = $response->toArray();

                    $data->setTemperature($json['main']['temp']       ?? 0.0);
                    $data->setFeelsLike($json['main']['feels_like']   ?? 0.0);
                    $data->setHumidity($json['main']['humidity']      ?? 0);

                    // m/s → km/h
                    $data->setWindSpeed(($json['wind']['speed'] ?? 0.0) * 3.6);

                    $data->setDescription($json['weather'][0]['description'] ?? 'N/A');
                    $data->setIconCode($json['weather'][0]['icon']           ?? '01d');
                    $data->setCityName($json['name']                         ?? self::CITY);

                    // Pluie : id météo 2xx=storm, 3xx=drizzle, 5xx=rain, 6xx=snow
                    $weatherId = $json['weather'][0]['id'] ?? 800;
                    $data->setRainExpected($weatherId >= 200 && $weatherId < 700);

                    $data->setAvailable(true);
                } else {
                    $data->setDescription('Erreur HTTP ' . $response->getStatusCode());
                }
            } catch (\Exception $e) {
                $data->setDescription('Erreur API météo: ' . $e->getMessage());
            }

            return $data;
        });
    }

    // ══════════════════════════════════════════════════════════════════
    //  ANALYSE PAR TÂCHE
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return Recommandation[]
     */
    public function analyserConditionsPourTache(Tache $tache, WeatherData $w): array
    {
        $recos = [];
        if (!$w->isAvailable()) return $recos;

        // ✅ FIX : getTypeTache() peut ne pas exister → getCategorie() en priorité
        $categorie = $tache->getCategorie();
        if ($categorie === null && method_exists($tache, 'getTypeTache')) {
            $categorie = $tache->getTypeTache();
        }
        $type  = strtoupper($categorie ?? '');

        $temp  = $w->getTemperature();
        $vent  = $w->getWindSpeed();
        $pluie = $w->isRainExpected();

        $bonVent = $vent >= 5 && $vent <= 25;
        $bonne   = !$pluie && $temp >= 15 && $temp <= 32 && $vent <= 35;

        switch ($type) {
            case 'TRAITEMENT':
                if ($pluie) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_DANGER, $tache, 'meteo.conditions.too_wet', 'METEO_PLUIE');
                } elseif ($vent > 40) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_DANGER, $tache, 'meteo.conditions.too_windy', 'METEO_VENT');
                } elseif ($bonVent && !$pluie && $temp >= 15 && $temp <= 30) {
                    $recos[] = $this->recoPositive($tache, 'METEO_POSITIVE');
                }
                break;

            case 'IRRIGATION':
                if ($pluie) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_WARNING, $tache, 'meteo.conditions.too_wet', 'METEO_PLUIE');
                } elseif ($temp > 35) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_WARNING, $tache, 'meteo.conditions.too_hot', 'METEO_CHALEUR');
                } elseif ($temp >= 18 && $temp <= 30 && !$pluie) {
                    $recos[] = $this->recoPositive($tache, 'METEO_POSITIVE');
                }
                break;

            case 'RECOLTE':
            case 'RÉCOLTE':
                if ($pluie) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_DANGER, $tache, 'meteo.conditions.too_wet', 'METEO_PLUIE');
                } elseif ($temp > 38) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_WARNING, $tache, 'meteo.conditions.too_hot', 'METEO_CHALEUR');
                } elseif ($bonne) {
                    $recos[] = $this->recoPositive($tache, 'METEO_POSITIVE');
                }
                break;

            case 'PLANTATION':
                if ($temp > 38) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_DANGER, $tache, 'meteo.conditions.too_hot', 'METEO_CHALEUR');
                } elseif ($pluie || $bonne) {
                    $recos[] = $this->recoPositive($tache, 'METEO_POSITIVE');
                }
                break;

            case 'LABOUR':
                if ($pluie) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_DANGER, $tache, 'meteo.conditions.too_wet', 'METEO_PLUIE');
                } elseif (!$pluie && $temp <= 30) {
                    $recos[] = $this->recoPositive($tache, 'METEO_POSITIVE');
                }
                break;

            case 'MAINTENANCE':
                if ($pluie || $vent > 50) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_WARNING, $tache, 'meteo.conditions.too_windy', 'METEO_INFO');
                } elseif ($bonne) {
                    $recos[] = $this->recoPositive($tache, 'METEO_POSITIVE');
                }
                break;

            default:
                if ($pluie && $vent > 30) {
                    $recos[] = $this->reco(Recommandation::NIVEAU_WARNING, $tache, 'meteo.conditions.too_wet', 'METEO_INFO');
                }
                break;
        }

        return $recos;
    }

    // ══════════════════════════════════════════════════════════════════
    //  RECOMMANDATIONS GÉNÉRALES (sans tâche)
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return Recommandation[]
     */
    public function genererRecommandationsGenerales(WeatherData $w): array
    {
        $recos = [];
        if (!$w->isAvailable()) return $recos;

        $temp  = $w->getTemperature();
        $vent  = $w->getWindSpeed();
        $pluie = $w->isRainExpected();

        if ($pluie) {
            $recos[] = new Recommandation(
                Recommandation::NIVEAU_WARNING,
                $this->translator->trans('meteo.advice.rain'),
                'GEN_RAIN'
            );
        } else {
            if ($temp >= 18 && $temp <= 28 && $vent < 15) {
                $recos[] = new Recommandation(
                    Recommandation::NIVEAU_POSITIVE,
                    $this->translator->trans('meteo.advice.ideal'),
                    'GEN_IDEAL'
                );
            }

            if ($vent < 10) {
                $recos[] = new Recommandation(
                    Recommandation::NIVEAU_POSITIVE,
                    $this->translator->trans('meteo.advice.wind_ok'),
                    'GEN_WIND_OK'
                );
            } elseif ($vent > 35) {
                $recos[] = new Recommandation(
                    Recommandation::NIVEAU_WARNING,
                    $this->translator->trans('meteo.advice.wind_bad'),
                    'GEN_WIND_BAD'
                );
            }

            if ($temp > 30) {
                $recos[] = new Recommandation(
                    Recommandation::NIVEAU_WARNING,
                    $this->translator->trans('meteo.advice.heat'),
                    'GEN_HEAT'
                );
            }

            if (empty($recos) && $temp > 15) {
                $recos[] = new Recommandation(
                    Recommandation::NIVEAU_POSITIVE,
                    $this->translator->trans('meteo.advice.generic_good'),
                    'GEN_GOOD'
                );
            }
        }

        return $recos;
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════════

    private function reco(string $niveau, Tache $tache, string $conditionKey, string $notifType): Recommandation
    {
        $message = match ($niveau) {
            Recommandation::NIVEAU_DANGER  => $this->translator->trans('meteo.alerts.danger',  [
                '%task%'   => $tache->getTitre(),
                '%reason%' => $this->translator->trans($conditionKey),
            ]),
            Recommandation::NIVEAU_WARNING => $this->translator->trans('meteo.alerts.caution', [
                '%task%'   => $tache->getTitre(),
                '%reason%' => $this->translator->trans($conditionKey),
            ]),
            default                        => $this->translator->trans('meteo.alerts.good', [
                '%task%' => $tache->getTitre(),
            ]),
        };

        return new Recommandation($niveau, $message, $notifType);
    }

    private function recoPositive(Tache $tache, string $notifType): Recommandation
    {
        return new Recommandation(
            Recommandation::NIVEAU_POSITIVE,
            $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
            $notifType
        );
    }
}