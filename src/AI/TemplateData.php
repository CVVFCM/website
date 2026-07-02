<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Pure helpers to map Sulu templateData values to LLM-friendly scalars.
 */
final class TemplateData
{
    public static function plainText(mixed $html): ?string
    {
        if (!\is_string($html)) {
            return null;
        }

        $text = trim(strip_tags($html));

        return '' !== $text ? $text : null;
    }

    /**
     * "Title, Town" from a Sulu location value.
     *
     * @param array<string, mixed> $data
     */
    public static function location(array $data): ?string
    {
        if (!\is_array($data['location'] ?? null)) {
            return null;
        }

        $parts = [];
        /** @var mixed $part */
        foreach ([$data['location']['title'] ?? null, $data['location']['town'] ?? null] as $part) {
            if (\is_string($part) && '' !== trim($part)) {
                $parts[] = trim($part);
            }
        }

        return [] !== $parts ? implode(', ', $parts) : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{titre: string, url: string}>
     */
    public static function links(array $data): array
    {
        $links = [];
        /** @var mixed $link */
        foreach ((array) ($data['links'] ?? []) as $link) {
            if (\is_array($link) && isset($link['title'], $link['url'])) {
                $links[] = [
                    'titre' => (string) $link['title'],
                    'url' => (string) $link['url'],
                ];
            }
        }

        return $links;
    }
}
