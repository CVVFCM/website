<?php

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

    public function __construct()
    {
        $this->id = Uuid::v6();
    }

    public function toArrayForML(): array
    {
        return [
            $this->recordedAt->format('Y-m-d H:i:s'),
            ...$this->getDayAsVector(),
            $this->humidity,
            $this->pressure,
            $this->temperature,
            ...$this->getWindDirectionAsVector(),
            $this->windSpeed,
            $this->windGusts,
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function getDayAsVector(): array
    {
        $yearDay = (int) $this->recordedAt->format('z');
        $dayAsRadians = 2 * pi() * ($yearDay / 365);

        return [
            sin($dayAsRadians),
            cos($dayAsRadians),
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function getWindDirectionAsVector(): array
    {
        $windDirectionAsRadian = deg2rad($this->windDirection);

        return [
            sin($windDirectionAsRadian),
            cos($windDirectionAsRadian),
        ];
    }

    public static function fromLiveWeather(\DateTimeImmutable $recordedAt, LiveWeather $liveWeather): self
    {
        $record = new self();
        $record->recordedAt = $recordedAt;
        $record->humidity = $liveWeather->humidity;
        $record->pressure = $liveWeather->pressure;
        $record->temperature = $liveWeather->temperature;
        $record->windDirection = $liveWeather->windDirection;
        $record->windSpeed = $liveWeather->windSpeed;
        $record->windGusts = $liveWeather->windGusts;

        return $record;
    }

    public static function fromArray(array $data): self
    {
        $record = new self();
        $record->recordedAt = $data['recordedAt'];
        $record->humidity = (float) $data['humidity'];
        $record->pressure = (float) $data['pressure'];
        $record->temperature = (float) $data['temperature'];
        $record->windDirection = (int) $data['windDirection'];
        $record->windSpeed = (float) $data['windSpeed'];
        $record->windGusts = (float) $data['windGusts'];

        return $record;
    }
}
