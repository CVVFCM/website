<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\Entity\ForgieUpload;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Content\ImageUrl;

/**
 * Turns a stored upload into an image content part fit for the model. A 16 MB photo
 * would blow past Mistral's per-request image budget, so oversized images are
 * downscaled with Imagick before being sent for vision. The original bytes are left
 * untouched (they are what gets attached to the admin email).
 *
 * Returns an ImageUrl carrying a base64 data URL: the Mistral bridge renders an
 * ImageUrl as `image_url: "<data-url>"` (the shape Mistral's vision API expects),
 * whereas a binary Image would fall back to the OpenAI-style `image_url: {url: …}`
 * object that Mistral ignores.
 */
final readonly class ForgieImagePreparer
{
    // Longest side / weight above which we downscale before sending to the model.
    private const int MAX_DIMENSION = 1568;
    private const int MAX_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function toModelImage(ForgieUpload $upload): ImageUrl
    {
        $binary = base64_decode($upload->data, true);
        if (false === $binary) {
            $binary = '';
        }

        $downscaled = $this->downscale($binary);

        return new ImageUrl(\sprintf(
            'data:%s;base64,%s',
            null !== $downscaled ? 'image/jpeg' : $upload->mimeType,
            base64_encode($downscaled ?? $binary),
        ));
    }

    /**
     * Returns downscaled JPEG bytes, or null when the image is already small enough
     * or Imagick is unavailable/failed (the caller then keeps the original).
     */
    private function downscale(string $binary): ?string
    {
        if ('' === $binary || !class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->readImageBlob($binary);

            $width = (int) $image->getImageWidth();
            $height = (int) $image->getImageHeight();
            $longestSide = max($width, $height);

            if ($longestSide <= self::MAX_DIMENSION && \strlen($binary) <= self::MAX_BYTES) {
                $image->clear();

                return null;
            }

            if ($longestSide > self::MAX_DIMENSION) {
                $scale = (float) self::MAX_DIMENSION / (float) $longestSide;
                $image->resizeImage((int) round((float) $width * $scale), (int) round((float) $height * $scale), \Imagick::FILTER_LANCZOS, 1);
            }

            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(85);
            $downscaled = (string) $image->getImageBlob();
            $image->clear();

            return $downscaled;
        } catch (\ImagickException $e) {
            $this->logger->warning('Forgie could not downscale an uploaded image; sending it as-is.', ['exception' => $e]);

            return null;
        }
    }
}
