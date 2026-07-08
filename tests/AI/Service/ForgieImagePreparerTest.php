<?php

declare(strict_types=1);

namespace App\Tests\AI\Service;

use App\AI\Service\ForgieImagePreparer;
use App\Entity\ForgieUpload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ForgieImagePreparerTest extends TestCase
{
    public function testItDownscalesAnOversizedImage(): void
    {
        $image = (new ForgieImagePreparer(new NullLogger()))->toModelImage($this->upload(3000, 2000));

        $probe = new \Imagick();
        $probe->readImageBlob($this->decodeDataUrl($image->getUrl()));

        $this->assertLessThanOrEqual(1568, max($probe->getImageWidth(), $probe->getImageHeight()));
    }

    public function testItKeepsASmallImageUntouched(): void
    {
        $upload = $this->upload(64, 48);

        $image = (new ForgieImagePreparer(new NullLogger()))->toModelImage($upload);

        $this->assertStringStartsWith('data:image/png;base64,', $image->getUrl());
        $probe = new \Imagick();
        $probe->readImageBlob($this->decodeDataUrl($image->getUrl()));
        $this->assertSame(64, $probe->getImageWidth());
        $this->assertSame(48, $probe->getImageHeight());
    }

    private function decodeDataUrl(string $dataUrl): string
    {
        $comma = strpos($dataUrl, ',');

        return base64_decode(substr($dataUrl, false === $comma ? 0 : $comma + 1), true) ?: '';
    }

    private function upload(int $width, int $height): ForgieUpload
    {
        $image = new \Imagick();
        $image->newImage($width, $height, new \ImagickPixel('red'));
        $image->setImageFormat('png');

        return new ForgieUpload(
            '019779c9-2f74-7a3e-8bcb-1d6c02f0d251',
            '019779c9-2f74-7a3e-8bcb-1d6c02f0d999',
            base64_encode((string) $image->getImageBlob()),
            'image/png',
            'photo.png',
            0,
            new \DateTimeImmutable(),
        );
    }
}
