<?php

declare(strict_types=1);

namespace App\Entity;

use App\DTO\LiveWeather;
use App\Repository\LiveWeatherRecordRepository;
use App\Weather\LiveWeatherComparison;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Uid\Uuid;

/**
 * @api
 *
 * @psalm-type CSVLiveWeathertData = array{
 *      recordedAt: \DateTimeImmutable,
 *      humidity: string,
 *      pressure: string,
 *      temperature: string,
 *      windDirection: string,
 *      windSpeed: string,
 *      windGusts: string,
 *  }
 */
#[Index(fields: ['recordedAt'])]
#[Entity(repositoryClass: LiveWeatherRecordRepository::class)]
#[Table]
readonly class LiveWeatherRecord
{
    public const string RESOURCE_KEY = 'live_weather_records';

    #[Id]
    #[GeneratedValue(strategy: 'NONE')]
    #[Column(type: 'uuid')]
    public Uuid $id;

    #[Column(type: Types::FLOAT)]
    public float $humidity;

    #[Column(type: Types::FLOAT)]
    public float $pressure;

    #[Column(type: Types::FLOAT)]
    public float $temperature;

    #[Column(type: Types::INTEGER)]
    public int $windDirection;

    #[Column(type: Types::FLOAT)]
    public float $windSpeed;

    #[Column(type: Types::FLOAT)]
    public float $windGusts;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $recordedAt;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    // Signed wind gaps of this observation vs the matching forecast and vs the ML correction of that
    // forecast. Speed gaps are percentages; direction gaps are degrees in (-180, 180]. Null when the
    // reference (forecast / model) was unavailable at import time.
    #[Column(type: Types::FLOAT, nullable: true)]
    public ?float $windSpeedGapForecast;

    #[Column(type: Types::FLOAT, nullable: true)]
    public ?float $windDirectionGapForecast;

    #[Column(type: Types::FLOAT, nullable: true)]
    public ?float $windSpeedGapModel;

    #[Column(type: Types::FLOAT, nullable: true)]
    public ?float $windDirectionGapModel;

    private function __construct(
        \DateTimeImmutable $recordedAt,
        float $humidity,
        float $pressure,
        float $temperature,
        int $windDirection,
        float $windSpeed,
        float $windGusts,
        ?float $windSpeedGapForecast = null,
        ?float $windDirectionGapForecast = null,
        ?float $windSpeedGapModel = null,
        ?float $windDirectionGapModel = null,
    ) {
        $this->id = Uuid::v6();
        $this->recordedAt = $recordedAt;
        $this->humidity = $humidity;
        $this->pressure = $pressure;
        $this->temperature = $temperature;
        $this->windDirection = $windDirection;
        $this->windSpeed = $windSpeed;
        $this->windGusts = $windGusts;
        $this->windSpeedGapForecast = $windSpeedGapForecast;
        $this->windDirectionGapForecast = $windDirectionGapForecast;
        $this->windSpeedGapModel = $windSpeedGapModel;
        $this->windDirectionGapModel = $windDirectionGapModel;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param CSVLiveWeathertData $data
     */
    public static function fromArray(array $data, ?LiveWeatherComparison $comparison = null): self
    {
        return new self(
            $data['recordedAt'],
            (float) $data['humidity'],
            (float) $data['pressure'],
            (float) $data['temperature'],
            (int) $data['windDirection'],
            (float) $data['windSpeed'],
            (float) $data['windGusts'],
            $comparison?->windSpeedGapForecast,
            $comparison?->windDirectionGapForecast,
            $comparison?->windSpeedGapModel,
            $comparison?->windDirectionGapModel,
        );
    }

    public static function fromLiveWeather(LiveWeather $liveWeather, ?LiveWeatherComparison $comparison = null): self
    {
        return new self(
            $liveWeather->updatedAt,
            $liveWeather->humidity,
            $liveWeather->pressure,
            $liveWeather->temperature,
            $liveWeather->windDirection,
            $liveWeather->windSpeed,
            $liveWeather->windGusts,
            $comparison?->windSpeedGapForecast,
            $comparison?->windDirectionGapForecast,
            $comparison?->windSpeedGapModel,
            $comparison?->windDirectionGapModel,
        );
    }
}
