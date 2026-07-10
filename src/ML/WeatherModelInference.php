<?php

declare(strict_types=1);

namespace App\ML;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs the weather-correction MLP in pure PHP: standard-scales the forecast/time features, feeds
 * them through {@see Mlp}, de-scales the outputs and turns the wind vector back into speed/bearing.
 *
 * Consumes the two JSON artifacts produced by ml/src/train_forecast_model.py:
 *  - model_weights.json : the dense layers (weights/biases).
 *  - scaler_params.json : StandardScaler mean/scale for inputs and targets.
 *
 * Artifacts are loaded lazily and cached, so the service (and anything depending on it) boots even
 * when the files are not present yet — see {@see tryPredict()} for the graceful path.
 */
final class WeatherModelInference
{
    /**
     * Feature order expected by {@see predict()} — must match FEATURE_COLS in the training script.
     */
    public const array FEATURE_COLS = [
        'forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos',
        'forecast_humidity', 'forecast_temperature',
        'day_sin', 'day_cos', 'hour_sin', 'hour_cos',
    ];

    // The model outputs a RESIDUAL wind vector; absolute wind is the forecast wind (carried in the
    // input features) plus that residual.
    private const int FORECAST_WIND_SIN_INDEX = 1;
    private const int FORECAST_WIND_COS_INDEX = 2;

    private ?Mlp $mlp = null;

    /** @var list<float> */
    private array $inputMean = [];
    /** @var list<float> */
    private array $inputScale = [];
    /** @var list<float> */
    private array $targetMean = [];
    /** @var list<float> */
    private array $targetScale = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/weather/ml/model_weights.json')]
        private readonly string $weightsPath,
        #[Autowire('%kernel.project_dir%/data/weather/ml/scaler_params.json')]
        private readonly string $scalerPath,
    ) {
    }

    /**
     * @param list<float> $features 9 values in {@see FEATURE_COLS} order
     *
     * @throws \RuntimeException when the model artifacts cannot be read
     */
    public function predict(array $features): WeatherPrediction
    {
        $mlp = $this->load();

        $normalized = [];
        foreach ($this->inputMean as $i => $mean) {
            $normalized[] = ($features[$i] - $mean) / $this->inputScale[$i];
        }

        $output = $mlp->forward($normalized);

        // De-scale the residual, then reconstruct the absolute wind vector: forecast + residual.
        $residualSin = $output[0] * $this->targetScale[0] + $this->targetMean[0];
        $residualCos = $output[1] * $this->targetScale[1] + $this->targetMean[1];
        $windSin = $features[self::FORECAST_WIND_SIN_INDEX] + $residualSin;
        $windCos = $features[self::FORECAST_WIND_COS_INDEX] + $residualCos;

        return new WeatherPrediction(
            sqrt($windSin * $windSin + $windCos * $windCos),
            (int) round(fmod(rad2deg(atan2($windSin, $windCos)) + 360.0, 360.0)) % 360,
        );
    }

    /**
     * Same as {@see predict()} but returns null instead of throwing when the model is unavailable
     * (artifacts not deployed yet). Callers can degrade to the raw forecast.
     *
     * @param list<float> $features
     */
    public function tryPredict(array $features): ?WeatherPrediction
    {
        try {
            return $this->predict($features);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function load(): Mlp
    {
        if (null !== $this->mlp) {
            return $this->mlp;
        }

        /** @var array{layers: list<array{weight: list<list<float>>, bias: list<float>, relu: bool}>} $weights */
        $weights = $this->decode($this->weightsPath);

        /** @var array{input: array{mean: list<float>, scale: list<float>}, target: array{mean: list<float>, scale: list<float>}} $scaler */
        $scaler = $this->decode($this->scalerPath);
        $this->inputMean = $scaler['input']['mean'];
        $this->inputScale = $scaler['input']['scale'];
        $this->targetMean = $scaler['target']['mean'];
        $this->targetScale = $scaler['target']['scale'];

        return $this->mlp = new Mlp($weights['layers']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        $raw = @file_get_contents($path);
        if (false === $raw) {
            throw new \RuntimeException(sprintf('Unable to read ML artifact "%s".', $path));
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
