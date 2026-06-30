<?php

declare(strict_types=1);

namespace App\Entity;

use App\DTO\LiveWeather;
use App\Repository\LiveWeatherRecordRepository;
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

    private function __construct(
        \DateTimeImmutable $recordedAt,
        float $humidity,
        float $pressure,
        float $temperature,
        int $windDirection,
        float $windSpeed,
        float $windGusts,
    ) {
        $this->id = Uuid::v6();
        $this->recordedAt = $recordedAt;
        $this->humidity = $humidity;
        $this->pressure = $pressure;
        $this->temperature = $temperature;
        $this->windDirection = $windDirection;
        $this->windSpeed = $windSpeed;
        $this->windGusts = $windGusts;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param CSVLiveWeathertData $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['recordedAt'],
            (float) $data['humidity'],
            (float) $data['pressure'],
            (float) $data['temperature'],
            (int) $data['windDirection'],
            (float) $data['windSpeed'],
            (float) $data['windGusts'],
        );
    }

    public static function fromLiveWeather(LiveWeather $liveWeather): self
    {
        return new self(
            $liveWeather->updatedAt,
            $liveWeather->humidity,
            $liveWeather->pressure,
            $liveWeather->temperature,
            $liveWeather->windDirection,
            $liveWeather->windSpeed,
            $liveWeather->windGusts,
        );
    }
}
