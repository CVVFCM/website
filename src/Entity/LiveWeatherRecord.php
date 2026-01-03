<?php

namespace App\Entity;

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

    /**
     * @param CSVLiveWeathertData $data
     */
    private function __construct(array $data)
    {
        $this->id = Uuid::v6();
        $this->recordedAt = $data['recordedAt'];
        $this->humidity = (float) $data['humidity'];
        $this->pressure = (float) $data['pressure'];
        $this->temperature = (float) $data['temperature'];
        $this->windDirection = (int) $data['windDirection'];
        $this->windSpeed = (float) $data['windSpeed'];
        $this->windGusts = (float) $data['windGusts'];
    }

    /**
     * @param CSVLiveWeathertData $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
