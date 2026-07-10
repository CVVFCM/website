<?php

declare(strict_types=1);

namespace App\Weather;

/**
 * Signed gaps between an observed wind and a reference (forecast or model prediction).
 */
final readonly class WindComparison
{
    /**
     * Signed relative gap in percent: (observed - reference) / reference * 100.
     * Null when the reference is 0 (undefined).
     */
    public static function speedGapPercent(float $observed, float $reference): ?float
    {
        if (0.0 === $reference) {
            return null;
        }

        return ($observed - $reference) / $reference * 100.0;
    }

    /**
     * Signed shortest angular difference in degrees, wrapped to (-180, 180].
     * Positive = observed is clockwise of the reference.
     */
    public static function directionGapDegrees(float $observed, float $reference): float
    {
        $diff = fmod($observed - $reference + 180.0, 360.0);
        if ($diff < 0.0) {
            $diff += 360.0;
        }

        return $diff - 180.0;
    }
}
