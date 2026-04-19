<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SoilDataService
{
    private HttpClientInterface $httpClient;
    private const SOILGRIDS_API_URL = 'https://rest.isric.org/soilgrids/v2.0/properties/query';

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Fetches soil properties for a given latitude and longitude from ISRIC SoilGrids.
     * Returns an array of soil layer data for 6 depth intervals.
     */
    public function fetchSoilData(float $lat, float $lon): array
    {
        $depths = ['0-5cm', '5-15cm', '15-30cm', '30-60cm', '60-100cm', '100-200cm'];
        $properties = ['phh2o', 'nitrogen', 'sand', 'clay', 'cec'];

        // Initialize layers
        $layers = [];
        foreach ($depths as $depth) {
            $layers[$depth] = [
                'depthLabel' => $depth,
                'phh2o' => null,
                'nitrogen' => null,
                'sand' => null,
                'clay' => null,
                'cec' => null,
            ];
        }

        try {
            $response = $this->httpClient->request('GET', self::SOILGRIDS_API_URL, [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lon,
                    'property' => $properties,
                    'depth' => $depths,
                    'value' => 'mean',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return $this->fallbackDemoData();
            }

            $data = $response->toArray(false);

            if (!isset($data['properties']['layers'])) {
                return $this->fallbackDemoData();
            }

            foreach ($data['properties']['layers'] as $layer) {
                $propName = $layer['name'] ?? null;
                if (!$propName || !in_array($propName, $properties)) {
                    continue;
                }

                foreach ($layer['depths'] ?? [] as $depthEntry) {
                    $depthLabel = $depthEntry['label'] ?? null;
                    $meanValue = $depthEntry['values']['mean'] ?? null;

                    if ($depthLabel && isset($layers[$depthLabel]) && $meanValue !== null) {
                        // Apply unit conversions matching the JavaFX service
                        switch ($propName) {
                            case 'phh2o':
                                $layers[$depthLabel]['phh2o'] = $meanValue / 10.0;
                                break;
                            case 'nitrogen':
                                $layers[$depthLabel]['nitrogen'] = $meanValue;
                                break;
                            case 'sand':
                                $layers[$depthLabel]['sand'] = $meanValue / 10.0;
                                break;
                            case 'clay':
                                $layers[$depthLabel]['clay'] = $meanValue / 10.0;
                                break;
                            case 'cec':
                                $layers[$depthLabel]['cec'] = $meanValue / 10.0;
                                break;
                        }
                    }
                }
            }

            // Propagate from layer above if needed to prevent nulls
            $layerValues = array_values($layers);
            for ($i = 1; $i < count($layerValues); $i++) {
                foreach ($properties as $prop) {
                    if ($layerValues[$i][$prop] === null && $layerValues[$i - 1][$prop] !== null) {
                        $layerValues[$i][$prop] = $layerValues[$i - 1][$prop];
                    }
                }
            }

            // Check if all values are null (no data available)
            $allNull = true;
            foreach ($layerValues as $lv) {
                if ($lv['phh2o'] !== null || $lv['nitrogen'] !== null || $lv['sand'] !== null || $lv['clay'] !== null || $lv['cec'] !== null) {
                    $allNull = false;
                    break;
                }
            }

            return $allNull ? $this->fallbackDemoData() : $layerValues;
        } catch (\Exception $e) {
            return $this->fallbackDemoData();
        }
    }

    /**
     * Demo data fallback when the API is unreachable or returns no data.
     */
    private function fallbackDemoData(): array
    {
        $depths = ['0-5cm', '5-15cm', '15-30cm', '30-60cm', '60-100cm', '100-200cm'];
        $phV = [7.2, 7.4, 7.6, 7.8, 8.0, 8.1];
        $nV = [230, 195, 160, 110, 70, 40];
        $sandV = [38.5, 35.2, 30.1, 25.8, 22.3, 20.0];
        $clayV = [28.0, 31.5, 35.0, 38.2, 41.0, 43.5];
        $cecV = [22.4, 20.1, 18.5, 16.2, 14.8, 13.1];

        $layers = [];
        for ($i = 0; $i < count($depths); $i++) {
            $layers[] = [
                'depthLabel' => $depths[$i],
                'phh2o' => $phV[$i],
                'nitrogen' => $nV[$i],
                'sand' => $sandV[$i],
                'clay' => $clayV[$i],
                'cec' => $cecV[$i],
            ];
        }
        return $layers;
    }
}
