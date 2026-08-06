<?php

declare(strict_types=1);

namespace App\AI;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;

/**
 * Board members exposed to Forgie: every Sulu contact carrying the "Forgie" tag.
 * Visibility is managed in the back office by adding/removing the tag on a contact.
 */
final readonly class BoardMemberRepository
{
    private const string VISIBILITY_TAG = 'Forgie';

    /**
     * Keywords looked up in the normalised position label (lowercase, unaccented),
     * most specific first: "Vice-présidente déléguée" must not match "president".
     */
    private const array POSITION_PRIORITY = [
        'vice president' => 1,
        'secretaire general' => 2,
        'secretaire' => 3,
        'tresorier' => 4,
        'president' => 0,
    ];

    private const array ACCENTS = [
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ä' => 'a',
        'ç' => 'c',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ó' => 'o',
        'ô' => 'o',
        'ö' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ÿ' => 'y',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{nom: string, fonction: ?string, email: ?string}>
     */
    public function findBoardMembers(): array
    {
        // Every account link is fetched, not only the one flagged "main": in the back
        // office a contact often carries its position on a secondary link, or on the
        // only link it has without the flag being set.
        /** @var list<array{id: int, firstName: string, lastName: string, email: ?string, fonction: ?string, principal: bool|int|string|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                'c.id AS id',
                'c.firstName AS firstName',
                'c.lastName AS lastName',
                'c.mainEmail AS email',
                'p.position AS fonction',
                'ac.main AS principal',
            )
            ->from(Contact::class, 'c')
            ->innerJoin('c.tags', 't', 'WITH', 't.name = :tag')
            ->leftJoin('c.accountContacts', 'ac')
            ->leftJoin('ac.position', 'p')
            ->setParameter('tag', self::VISIBILITY_TAG)
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, array{nom: string, fonction: ?string, email: ?string}> $members */
        $members = [];
        /** @var array<int, bool> $fromMainLink */
        $fromMainLink = [];

        foreach ($rows as $row) {
            $id = $row['id'];

            if (!isset($members[$id])) {
                $members[$id] = [
                    'nom' => trim($row['firstName'].' '.$row['lastName']),
                    'fonction' => null,
                    'email' => $row['email'],
                ];
                $fromMainLink[$id] = false;
            }

            $position = null !== $row['fonction'] ? trim($row['fonction']) : '';

            if ('' === $position) {
                continue;
            }

            $isMain = (bool) $row['principal'];

            // First position wins, unless a later one comes from the main link.
            if (null === $members[$id]['fonction'] || ($isMain && !$fromMainLink[$id])) {
                $members[$id]['fonction'] = $position;
                $fromMainLink[$id] = $isMain;
            }
        }

        $sorted = array_values($members);

        usort(
            $sorted,
            /**
             * @param array{nom: string, fonction: ?string, email: ?string} $a
             * @param array{nom: string, fonction: ?string, email: ?string} $b
             */
            static function (array $a, array $b): int {
                $priorityA = self::positionPriority($a['fonction']);
                $priorityB = self::positionPriority($b['fonction']);

                return $priorityA <=> $priorityB ?: strcmp($a['nom'], $b['nom']);
            },
        );

        return $sorted;
    }

    /**
     * Unrecognised (or missing) positions are listed last, in alphabetical order.
     */
    private static function positionPriority(?string $fonction): int
    {
        if (null === $fonction) {
            return \PHP_INT_MAX;
        }

        $normalized = self::normalize($fonction);

        foreach (self::POSITION_PRIORITY as $keyword => $priority) {
            if (str_contains($normalized, $keyword)) {
                return $priority;
            }
        }

        return \PHP_INT_MAX;
    }

    /**
     * "Vice-Présidente déléguée" => "vice presidente deleguee": the back office
     * labels vary in case, accents, punctuation and complements.
     */
    private static function normalize(string $label): string
    {
        $unaccented = strtr(mb_strtolower($label), self::ACCENTS);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $unaccented) ?? $unaccented);
    }
}
