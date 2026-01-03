<?php

namespace App\Entity;

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

    /**
     * @param CSVForecastData $data
     */
    private function __construct(array $data)
    {
        $this->id = Uuid::v6();
        $this->date = $data['date'];
        $this->humidity = (float) $data['humidity'];
        $this->pressure = (float) $data['pressure'];
        $this->temperature = (float) $data['temperature'];
        $this->windDirection = (int) $data['windDirection'];
        $this->windSpeed = (float) $data['windSpeed'];
    }

    /**
     * @param CSVForecastData $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
