<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\LegalDocumentRepository;
use App\AI\Tool\LegalDocumentTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LegalDocumentToolTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function documents(): iterable
    {
        yield 'statuts' => ['statuts', 'Statuts du CVVFCM'];
        yield 'règlement intérieur' => ['reglement_interieur', 'Règlement intérieur du CVVFCM'];
        yield 'règlement du lac' => ['reglement_lac', 'Règlement de la police de la navigation du lac des Vieilles Forges'];
    }

    #[DataProvider('documents')]
    public function testItReturnsTheFullDocument(string $documentKey, string $expectedTitle): void
    {
        $result = ($this->tool())($documentKey);

        self::assertSame(['document', 'reference', 'contenu'], array_keys($result));
        self::assertSame($expectedTitle, $result['document']);
        self::assertNotEmpty($result['reference']);
        self::assertIsString($result['contenu']);
        self::assertGreaterThan(1000, \strlen($result['contenu']));
    }

    public function testStatutsContainTheCommitteeCompositionRule(): void
    {
        $result = ($this->tool())('statuts');

        self::assertIsString($result['contenu']);
        self::assertStringContainsString('Article 1', $result['contenu']);
        self::assertStringContainsString('Comité de Direction de 6 à 15 membres', $result['contenu']);
    }

    public function testLakeRulesForbidMotorboats(): void
    {
        $result = ($this->tool())('reglement_lac');

        self::assertIsString($result['contenu']);
        self::assertStringContainsString('ski nautique', $result['contenu']);
        self::assertStringContainsString('interdites', $result['contenu']);
    }

    public function testUnknownDocumentKeyYieldsNull(): void
    {
        self::bootKernel();
        /** @var LegalDocumentRepository $repository */
        $repository = static::getContainer()->get(LegalDocumentRepository::class);

        self::assertNull($repository->findByDocumentKey('inexistant'));
    }

    private function tool(): LegalDocumentTool
    {
        self::bootKernel();

        return static::getContainer()->get(LegalDocumentTool::class);
    }
}
