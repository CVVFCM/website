<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class ErrorPageTest extends KernelTestCase
{
    public function testItRendersAFrench404PageWithAWayHome(): void
    {
        $html = $this->render(404, 'Not Found');

        $this->assertStringContainsString('Page introuvable', $html);
        $this->assertStringContainsString('Erreur 404', $html);
        $this->assertStringContainsString('Retour à l\'accueil', $html);
        $this->assertStringContainsString('/evenements', $html);
        $this->assertStringContainsString('/contact', $html);
        $this->assertStringContainsString('Il ne fallait pas louper les cours', $html);
        $this->assertStringContainsString('ErrorPage__illustration', $html);
    }

    public function testItRendersAGenericMessageForOtherStatusCodes(): void
    {
        $html = $this->render(500, 'Internal Server Error');

        $this->assertStringContainsString('Une erreur est survenue', $html);
        $this->assertStringContainsString('Erreur 500', $html);
        $this->assertStringContainsString('Retour à l\'accueil', $html);
        $this->assertStringNotContainsString('Page introuvable', $html);
        $this->assertStringNotContainsString('Il ne fallait pas louper les cours', $html);
        $this->assertStringNotContainsString('ErrorPage__illustration', $html);
    }

    private function render(int $statusCode, string $statusText): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('error/_error_content.html.twig', [
            'status_code' => $statusCode,
            'status_text' => $statusText,
        ]);
    }
}
