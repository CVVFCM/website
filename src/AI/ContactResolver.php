<?php

declare(strict_types=1);

namespace App\AI;

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\ContactRepositoryInterface;

/**
 * Resolves Sulu contact selections to "Name (email)" strings for the AI tools.
 * Handles both contact_account_selection entries ("c<id>"/"a<id>", contacts only)
 * and contact_selection entries (plain int ids).
 */
final readonly class ContactResolver
{
    public function __construct(
        private ContactRepositoryInterface $contactRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolve(mixed $selection): array
    {
        $contacts = [];
        /** @var mixed $entry */
        foreach ((array) ($selection ?? []) as $entry) {
            $id = $this->contactId($entry);
            if (null === $id) {
                continue;
            }

            $contact = $this->contactRepository->find($id);
            if (!$contact instanceof Contact) {
                continue;
            }

            $email = $contact->getMainEmail();
            $contacts[] = null !== $email && '' !== $email
                ? sprintf('%s (%s)', $contact->getFullName(), $email)
                : $contact->getFullName();
        }

        return $contacts;
    }

    private function contactId(mixed $entry): ?int
    {
        if (\is_int($entry)) {
            return $entry;
        }

        if (\is_string($entry) && str_starts_with($entry, 'c')) {
            return (int) substr($entry, 1);
        }

        return null;
    }
}
