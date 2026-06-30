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
    ) {
        $this->id = Uuid::v6();
        $this->humidity = $humidity;
        $this->pressure = $pressure;
        $this->temperature = $temperature;
        $this->windDirection = $windDirection;
        $this->windSpeed = $windSpeed;
        $this->date = $date;
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
        );
    }
}
