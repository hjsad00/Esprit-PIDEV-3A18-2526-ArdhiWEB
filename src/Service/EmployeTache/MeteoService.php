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

class MeteoService
{
    private const CITY = 'Tunis,TN';
    private const BASE_URL = 'https://api.openweathermap.org/data/2.5/weather';

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        #[Autowire(env: 'OPENWEATHER_API_KEY')]
        private string $apiKey
    ) {
    }

    /**
     * Récupère la météo actuelle (avec cache 5 min).
     */
    public function getCurrentWeather(): WeatherData
    {
        return $this->cache->get('meteo_current_data', function (ItemInterface $item) {
            $item->expiresAfter(300); // Cache 5 minutes

            $data = new WeatherData();
            try {
                $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
                $response = $this->client->request('GET', self::BASE_URL, [
                    'query' => [
                        'q' => self::CITY,
                        'units' => 'metric',
                        'lang' => $locale,
                        'appid' => $this->apiKey
                    ],
                    'timeout' => 5
                ]);

                if ($response->getStatusCode() === 200) {
                    $json = $response->toArray();

                    $data->setTemperature($json['main']['temp'] ?? 0.0);
                    $data->setFeelsLike($json['main']['feels_like'] ?? 0.0);
                    $data->setHumidity($json['main']['humidity'] ?? 0);
                    
                    // vent (m/s → km/h)
                    $windMs = $json['wind']['speed'] ?? 0.0;
                    $data->setWindSpeed($windMs * 3.6);

                    $data->setDescription($json['weather'][0]['description'] ?? 'N/A');
                    $data->setIconCode($json['weather'][0]['icon'] ?? '01d');
                    $data->setCityName($json['name'] ?? self::CITY);

                    // pluie : détectée via id météo (2xx=storm, 3xx=drizzle, 5xx=rain, 6xx=snow)
                    $weatherId = $json['weather'][0]['id'] ?? 800;
                    $rain = ($weatherId >= 200 && $weatherId < 700);
                    $data->setRainExpected($rain);

                    $data->setAvailable(true);
                } else {
                    $data->setDescription('Erreur HTTP ' . $response->getStatusCode());
                }
            } catch (\Exception $e) {
                $data->setDescription('Erreur: ' . $e->getMessage());
            }

            return $data;
        });
    }

    /**
     * Analyse intelligente : retourne des recommandations POSITIVES et NÉGATIVES
     * en fonction des conditions météo et du type de tâche.
     * 
     * @return Recommandation[]
     */
    public function analyserConditionsPourTache(Tache $tache, WeatherData $w): array
    {
        $recos = [];
        if (!$w->isAvailable()) return $recos;

        $type = strtoupper($tache->getCategorie() ?? $tache->getTypeTache());
        $temp = $w->getTemperature();
        $vent = $w->getWindSpeed();
        $pluie = $w->isRainExpected();
        
        $bonVent = $vent >= 5 && $vent <= 25;
        $bonne = !$pluie && $temp >= 15 && $temp <= 32 && $vent <= 35;

        switch ($type) {
            case 'TRAITEMENT':
                if ($pluie) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_DANGER,
                        $this->translator->trans('meteo.alerts.danger', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_wet')]),
                        "METEO_PLUIE");
                } elseif ($vent > 40) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_DANGER,
                        $this->translator->trans('meteo.alerts.danger', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_windy')]),
                        "METEO_VENT");
                } elseif ($bonVent && !$pluie && $temp >= 15 && $temp <= 30) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                }
                break;

            case 'IRRIGATION':
                if ($pluie) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_WARNING,
                        $this->translator->trans('meteo.alerts.caution', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_wet')]),
                        "METEO_PLUIE");
                } elseif ($temp > 35) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_WARNING,
                        $this->translator->trans('meteo.alerts.caution', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_hot')]),
                        "METEO_CHALEUR");
                } elseif ($temp >= 18 && $temp <= 30 && !$pluie) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                }
                break;

            case 'RECOLTE':
            case 'RÉCOLTE':
                if ($pluie) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_DANGER,
                        $this->translator->trans('meteo.alerts.danger', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_wet')]),
                        "METEO_PLUIE");
                } elseif ($temp > 38) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_WARNING,
                        $this->translator->trans('meteo.alerts.caution', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_hot')]),
                        "METEO_CHALEUR");
                } elseif ($bonne) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                }
                break;

            case 'PLANTATION':
                if ($temp > 38) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_DANGER,
                        $this->translator->trans('meteo.alerts.danger', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_hot')]),
                        "METEO_CHALEUR");
                } elseif ($pluie) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                } elseif ($bonne) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                }
                break;

            case 'LABOUR':
                if ($pluie) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_DANGER,
                        $this->translator->trans('meteo.alerts.danger', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_wet')]),
                        "METEO_PLUIE");
                } elseif (!$pluie && $temp <= 30) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                }
                break;

            case 'MAINTENANCE':
                if ($pluie || $vent > 50) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_WARNING,
                        $this->translator->trans('meteo.alerts.caution', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_windy')]),
                        "METEO_INFO");
                } elseif ($bonne) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_POSITIVE,
                        $this->translator->trans('meteo.alerts.good', ['%task%' => $tache->getTitre()]),
                        "METEO_POSITIVE");
                }
                break;

            default:
                if ($pluie && $vent > 30) {
                    $recos[] = new Recommandation(Recommandation::NIVEAU_WARNING,
                        $this->translator->trans('meteo.alerts.caution', ['%task%' => $tache->getTitre(), '%reason%' => $this->translator->trans('meteo.conditions.too_wet')]),
                        "METEO_INFO");
                }
                break;
        }

        return $recos;
    }
}
