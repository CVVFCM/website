<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\BoardMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against the fixtures loaded in the test database (see ContactsFixtures):
 * five contacts carry the "Forgie" tag, one does not.
 */
final class BoardMemberRepositoryTest extends KernelTestCase
{
    public function testItReadsThePositionOfAnAccountLinkThatIsNotFlaggedMain(): void
    {
        $members = $this->findByName();

        self::assertArrayHasKey('Claire Lefèvre', $members);
        self::assertSame('Vice-Présidente déléguée', $members['Claire Lefèvre']['fonction']);
        self::assertSame('claire@cvvfcm.fr', $members['Claire Lefèvre']['email']);
    }

    public function testItOrdersMembersByPosition(): void
    {
        $names = array_map(
            static fn (array $member): string => $member['nom'],
            $this->repository()->findBoardMembers(),
        );

        self::assertSame(
            [
                'Yohan Giarelli',            // Président
                'Claire Lefèvre',            // Vice-Présidente déléguée
                'Thomas Van Den Schrieck',   // Secrétaire général
                'Baptiste Gilles-Carret',    // Trésorier
                'Alice Moreau',              // no position: listed last
            ],
            $names,
        );
    }

    public function testItRecognisesAPositionLabelWithADifferentCaseOrAComplement(): void
    {
        $names = array_map(
            static fn (array $member): string => $member['nom'],
            $this->repository()->findBoardMembers(),
        );

        // "Vice-Présidente déléguée" must rank as a vice-president: right after the
        // president, before the secretary — and never as a president.
        self::assertSame(1, array_search('Claire Lefèvre', $names, true));
    }

    public function testItIgnoresContactsWithoutTheForgieTag(): void
    {
        self::assertArrayNotHasKey('Paul Durand', $this->findByName());
    }

    /**
     * @return array<string, array{nom: string, fonction: ?string, email: ?string}>
     */
    private function findByName(): array
    {
        $indexed = [];
        foreach ($this->repository()->findBoardMembers() as $member) {
            self::assertSame(['nom', 'fonction', 'email'], array_keys($member));
            $indexed[$member['nom']] = $member;
        }

        return $indexed;
    }

    private function repository(): BoardMemberRepository
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Built by hand: the repository is only injected into BoardMemberTool, so the
        // container inlines it and the test container cannot hand it back.
        return new BoardMemberRepository($entityManager);
    }
}
