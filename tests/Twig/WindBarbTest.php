<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class WindBarbTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{float, int, int, int}>
     */
    public static function speedCases(): iterable
    {
        // speed in knots, expected pennants (50 kt), full barbs (10 kt), half-barbs (5 kt)
        yield 'calm, bare staff' => [2.0, 0, 0, 0];
        yield 'rounds up to a half-barb' => [3.0, 0, 0, 1];
        yield 'lone half-barb' => [5.0, 0, 0, 1];
        yield 'rounds down to a half-barb' => [7.0, 0, 0, 1];
        yield 'full and half' => [15.0, 0, 1, 1];
        yield 'rounds to twenty-five' => [27.0, 0, 2, 1];
        yield 'pennant and half' => [55.0, 1, 0, 1];
        yield 'pennant, full barbs and half' => [67.0, 1, 1, 1];
    }

    #[DataProvider('speedCases')]
    public function testItEncodesSpeedInFeathers(float $speed, int $pennants, int $fulls, int $halves): void
    {
        $svg = $this->render($speed, 0);

        $this->assertSame($pennants, \substr_count($svg, 'data-part="pennant"'));
        $this->assertSame($fulls, \substr_count($svg, 'data-part="barb"'));
        $this->assertSame($halves, \substr_count($svg, 'data-part="half-barb"'));
    }

    public function testItExposesTheRotationAsACustomProperty(): void
    {
        $this->assertStringContainsString('--wind-rotation: 225deg', $this->render(10.0, 225));
    }

    public function testItLabelsTheGraphic(): void
    {
        $svg = $this->render(10.0, 0);

        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringContainsString('aria-label="Vent de sud, 10 nœuds"', $svg);
    }

    private function render(float $speed, int $rotation): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('partials/_wind_barb.html.twig', [
            'speed' => $speed,
            'rotation' => $rotation,
            'label' => \sprintf('Vent de sud, %d nœuds', (int) \round($speed)),
            'class' => 'HomepageLive__windBarb',
        ]);
    }
}
