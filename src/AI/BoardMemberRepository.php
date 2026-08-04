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

    private const array POSITION_PRIORITY = [
        'Président' => 0,
        'Présidente' => 0,
        'Vice-président' => 1,
        'Vice-présidente' => 1,
        'Secrétaire général' => 2,
        'Secrétaire générale' => 2,
        'Secrétaire' => 3,
        'Trésorier' => 4,
        'Trésorière' => 4,
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
        /** @var list<array{firstName: string, lastName: string, fonction: ?string, email: ?string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('c.firstName', 'c.lastName', 'p.position AS fonction', 'c.mainEmail AS email')
            ->from(Contact::class, 'c')
            ->innerJoin('c.tags', 't', 'WITH', 't.name = :tag')
            ->leftJoin('c.accountContacts', 'ac', 'WITH', 'ac.main = true')
            ->leftJoin('ac.position', 'p')
            ->setParameter('tag', self::VISIBILITY_TAG)
            ->getQuery()
            ->getArrayResult();

        $members = array_map(
            static fn (array $row): array => [
                'nom' => trim($row['firstName'].' '.$row['lastName']),
                'fonction' => $row['fonction'],
                'email' => $row['email'],
            ],
            $rows,
        );

        usort(
            $members,
            /**
             * @param array{nom: string, fonction: ?string, email: ?string} $a
             * @param array{nom: string, fonction: ?string, email: ?string} $b
             */
            static function (array $a, array $b): int {
                $priorityA = self::POSITION_PRIORITY[$a['fonction'] ?? ''] ?? \PHP_INT_MAX;
                $priorityB = self::POSITION_PRIORITY[$b['fonction'] ?? ''] ?? \PHP_INT_MAX;

                return $priorityA <=> $priorityB ?: strcmp($a['nom'], $b['nom']);
            },
        );

        return $members;
    }
}
