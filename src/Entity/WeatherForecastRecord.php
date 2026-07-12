<?php

declare(strict_types=1);

namespace App\Entity;

use App\DTO\WeatherForecast;
use App\Repository\WeatherForecastRecordRepository;
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
 * @psalm-type CSVForecastData = array{
 *     date: \DateTimeImmutable,
 *     humidity: string,
 *     pressure: string,
 *     temperature: string,
 *     windDirection: string,
 *     windSpeed: string,
 * }
 */
#[Index(fields: ['date'])]
#[Entity(repositoryClass: WeatherForecastRecordRepository::class)]
#[Table]
readonly class WeatherForecastRecord
{
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

    // WMO weather interpretation code (open-meteo). Nullable: CSV-imported rows and rows written before
    // this column existed have none — {@see toWeatherForecast} coalesces null to 0 (clear sky).
    #[Column(type: Types::INTEGER, nullable: true)]
    public ?int $weatherCode;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $date;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    private function __construct(
        float $humidity,
        float $pressure,
        float $temperature,
        int $windDirection,
        float $windSpeed,
        \DateTimeImmutable $date,
        ?int $weatherCode = null,
    ) {
        $this->id = Uuid::v6();
        $this->humidity = $humidity;
        $this->pressure = $pressure;
        $this->temperature = $temperature;
        $this->windDirection = $windDirection;
        $this->windSpeed = $windSpeed;
        $this->date = $date;
        $this->weatherCode = $weatherCode;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param CSVForecastData $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) $data['humidity'],
            (float) $data['pressure'],
            (float) $data['temperature'],
            (int) $data['windDirection'],
            (float) $data['windSpeed'],
            $data['date'],
        );
    }

    public static function fromWeatherForecast(WeatherForecast $forecast): self
    {
        return new self(
            $forecast->humidity,
            $forecast->pressure,
            $forecast->temperature,
            $forecast->windDirection,
            $forecast->windSpeed,
            $forecast->date,
            $forecast->weatherCode,
        );
    }

    /**
     * Rebuild the value object the templates consume. Precipitation is not persisted (unused on the
     * homepage) so it defaults to 0.0; a missing weather code degrades to 0 (clear sky).
     */
    public function toWeatherForecast(): WeatherForecast
    {
        return new WeatherForecast(
            $this->date,
            $this->temperature,
            $this->pressure,
            (int) $this->humidity,
            0.0,
            $this->windSpeed,
            $this->windDirection,
            $this->weatherCode ?? 0,
        );
    }
}
