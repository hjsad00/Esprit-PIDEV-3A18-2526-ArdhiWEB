<?php

namespace App\Model\Meteo;

class WeatherData
{
    private bool $available = false;
    private float $temperature = 0.0;
    private float $feelsLike = 0.0;
    private int $humidity = 0;
    private float $windSpeed = 0.0;
    private string $description = '';
    private string $iconCode = '';
    private string $cityName = '';
    private bool $rainExpected = false;

    public function isAvailable(): bool { return $this->available; }
    public function setAvailable(bool $v): self { $this->available = $v; return $this; }

    public function getTemperature(): float { return $this->temperature; }
    public function setTemperature(float $v): self { $this->temperature = $v; return $this; }

    public function getFeelsLike(): float { return $this->feelsLike; }
    public function setFeelsLike(float $v): self { $this->feelsLike = $v; return $this; }

    public function getHumidity(): int { return $this->humidity; }
    public function setHumidity(int $v): self { $this->humidity = $v; return $this; }

    public function getWindSpeed(): float { return $this->windSpeed; }
    public function setWindSpeed(float $v): self { $this->windSpeed = $v; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): self { $this->description = $v; return $this; }

    public function getIconCode(): string { return $this->iconCode; }
    public function setIconCode(string $v): self { $this->iconCode = $v; return $this; }

    public function getCityName(): string { return $this->cityName; }
    public function setCityName(string $v): self { $this->cityName = $v; return $this; }

    public function isRainExpected(): bool { return $this->rainExpected; }
    public function setRainExpected(bool $v): self { $this->rainExpected = $v; return $this; }
}
