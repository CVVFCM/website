<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class LinksAndContactsTest extends KernelTestCase
{
    private const LINKS = [
        ['title' => 'Tableau Officiel', 'url' => 'https://drive.google.com/officiel', 'target_blank' => true],
        ['title' => 'CVVFCM', 'url' => 'https://cvvfcm.fr', 'target_blank' => false],
    ];

    private const CONTACTS = [[
        'fullName' => 'Thomas Van Den Schrieck',
        'positionName' => 'Secrétaire général',
        'contactDetails' => ['emails' => [['email' => 'thomas@cvvfcm.fr']]],
    ]];

    public function testItRendersBothPanels(): void
    {
        $html = $this->render(self::LINKS, self::CONTACTS);

        $this->assertSame(2, substr_count($html, 'class="EventContentBlock"'));
        $this->assertStringContainsString('Liens utiles', $html);
        $this->assertStringContainsString('Contacts', $html);
    }

    public function testItRendersOnlyTheLinksWhenThereIsNoContact(): void
    {
        $html = $this->render(self::LINKS, null);

        $this->assertSame(1, substr_count($html, 'class="EventContentBlock"'));
        $this->assertStringContainsString('Liens utiles', $html);
        $this->assertStringNotContainsString('Contacts', $html);
    }

    public function testItRendersOnlyTheContactsWhenThereIsNoLink(): void
    {
        $html = $this->render(null, self::CONTACTS);

        $this->assertSame(1, substr_count($html, 'class="EventContentBlock"'));
        $this->assertStringContainsString('Contacts', $html);
        $this->assertStringNotContainsString('Liens utiles', $html);
    }

    public function testItRendersNothingWhenBothAreEmpty(): void
    {
        $this->assertSame('', trim($this->render(null, null)));
    }

    public function testOnlyTheFlaggedLinkOpensInANewTab(): void
    {
        $html = $this->render(self::LINKS, null);

        $this->assertSame(1, substr_count($html, 'target="_blank"'));
        $this->assertStringContainsString('href="https://drive.google.com/officiel" title="Tableau Officiel" target="_blank"', $html);
    }

    public function testTheContactEmailIsAMailtoLink(): void
    {
        $html = $this->render(null, self::CONTACTS);

        $this->assertStringContainsString('<a href="mailto:thomas@cvvfcm.fr">thomas@cvvfcm.fr</a>', $html);
    }

    /**
     * @param ?list<array<string, mixed>> $links
     * @param ?list<array<string, mixed>> $contacts
     */
    private function render(?array $links, ?array $contacts): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('partials/_links_and_contacts.html.twig', [
            'links' => $links,
            'contacts' => $contacts,
        ]);
    }
}
