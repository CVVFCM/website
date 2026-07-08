<?php

declare(strict_types=1);

/*
 * Minimal Psalm stub for the ext-imagick members used by App\AI\Service\ForgieImagePreparer.
 * The CI Psalm image has no ext-imagick loaded, so without this stub the Imagick class is
 * unknown there. Parsed by Psalm only — never autoloaded or executed.
 */

class Imagick
{
    public const FILTER_LANCZOS = 22;

    public function __construct(?string $files = null)
    {
    }

    public function readImageBlob(string $image, ?string $filename = null): bool
    {
    }

    public function getImageWidth(): int
    {
    }

    public function getImageHeight(): int
    {
    }

    public function resizeImage(int $columns, int $rows, int $filter, float $blur, bool $bestfit = false, bool $legacy = false): bool
    {
    }

    public function setImageFormat(string $format): bool
    {
    }

    public function setImageCompressionQuality(int $quality): bool
    {
    }

    public function getImageBlob(): string
    {
    }

    public function clear(): bool
    {
    }
}

class ImagickException extends Exception
{
}
