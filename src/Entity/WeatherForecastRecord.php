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

    public function __construct()
    {
        $this->id = Uuid::v6();
    }

    public static function fromArray(array $data): self
    {
        $record = new self();
        $record->date = $data['date'];
        $record->humidity = (float) $data['humidity'];
        $record->pressure = (float) $data['pressure'];
        $record->temperature = (float) $data['temperature'];
        $record->windDirection = (int) $data['windDirection'];
        $record->windSpeed = (float) $data['windSpeed'];

        return $record;
    }
}
