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
// Not a `readonly class`: opcache preload compiles Doctrine's generated proxy, which is non-readonly
// and may not extend a readonly class. Per-property `readonly` keeps the same immutability guarantee.
#[Index(fields: ['recordedAt'])]
#[Entity(repositoryClass: LiveWeatherRecordRepository::class)]
#[Table]
class LiveWeatherRecord
{
    public const string RESOURCE_KEY = 'live_weather_records';

    #[Id]
    #[GeneratedValue(strategy: 'NONE')]
    #[Column(type: 'uuid')]
    public readonly Uuid $id;

    // Null on rows imported as hourly means: those historical files carry the wind and nothing else.
    // The station itself always reports all of them, so a null here means "not measured", never
    // "measured as zero" — {@see toLiveWeather()} and the templates keep that distinction visible.
    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $humidity;

    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $pressure;

    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $temperature;

    #[Column(type: Types::INTEGER)]
    public readonly int $windDirection;

    #[Column(type: Types::FLOAT)]
    public readonly float $windSpeed;

    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $windGusts;

    // True when the row is already an hourly mean rather than one of the ten-minute samples the
    // station writes. The ML export requires six samples to trust an hour; these rows stand alone
    // and bypass that count, so the flag has to travel with them.
    #[Column(type: Types::BOOLEAN, options: ['default' => false])]
    public readonly bool $hourlyMean;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $recordedAt;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $createdAt;

    // Signed wind gaps of this observation vs the matching forecast and vs the ML correction of that
    // forecast. Speed gaps are percentages; direction gaps are degrees in (-180, 180]. Null when the
    // reference (forecast / model) was unavailable at import time.
    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $windSpeedGapForecast;

    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $windDirectionGapForecast;

    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $windSpeedGapModel;

    #[Column(type: Types::FLOAT, nullable: true)]
    public readonly ?float $windDirectionGapModel;

    private function __construct(
        \DateTimeImmutable $recordedAt,
        ?float $humidity,
        ?float $pressure,
        ?float $temperature,
        int $windDirection,
        float $windSpeed,
        ?float $windGusts,
        ?float $windSpeedGapForecast = null,
        ?float $windDirectionGapForecast = null,
        ?float $windSpeedGapModel = null,
        ?float $windDirectionGapModel = null,
        bool $hourlyMean = false,
    ) {
        $this->hourlyMean = $hourlyMean;
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

    /**
     * A historical hour whose wind is already averaged. Those files carry the (sin·speed, cos·speed)
     * projection and nothing else, so every other measurement stays null. Speed and bearing are
     * recovered from the vector — exact, up to the whole-degree rounding the column imposes, which
     * is the resolution the station reports anyway.
     */
    public static function fromHourlyWindMean(\DateTimeImmutable $hour, float $windSin, float $windCos): self
    {
        return new self(
            $hour,
            null,
            null,
            null,
            (int) round(fmod(rad2deg(atan2($windSin, $windCos)) + 360.0, 360.0)) % 360,
            sqrt($windSin * $windSin + $windCos * $windCos),
            null,
            hourlyMean: true,
        );
    }

    /**
     * Rebuild the value object the homepage variants consume. Only instantaneous values are persisted,
     * so the min/max/average/rain/solar fields (shown only on the live page, which still uses the live
     * provider) are filled from the stored values as neutral placeholders.
     */
    public function toLiveWeather(): LiveWeather
    {
        $liveWeather = new LiveWeather();
        $liveWeather->updatedAt = $this->recordedAt;
        $liveWeather->humidity = $this->humidity;
        $liveWeather->humidityMin = $this->humidity;
        $liveWeather->humidityMax = $this->humidity;
        $liveWeather->pressure = $this->pressure;
        $liveWeather->pressureMin = $this->pressure;
        $liveWeather->pressureMax = $this->pressure;
        $liveWeather->rainRate = 0.0;
        $liveWeather->rainTotal = 0.0;
        $liveWeather->solarRadiation = 0;
        $liveWeather->temperature = $this->temperature;
        $liveWeather->temperatureMin = $this->temperature;
        $liveWeather->temperatureMax = $this->temperature;
        $liveWeather->windDirection = $this->windDirection;
        $liveWeather->windSpeed = $this->windSpeed;
        $liveWeather->windGusts = $this->windGusts;
        $liveWeather->windDirectionAverage = $this->windDirection;
        $liveWeather->windSpeedAverage = $this->windSpeed;
        $liveWeather->windSpeedMax = $this->windSpeed;
        $liveWeather->windSpeedMin = $this->windSpeed;

        return $liveWeather;
    }
}
