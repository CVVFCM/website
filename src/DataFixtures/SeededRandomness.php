<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Makes the randomised fixtures reproducible, which the screenshot baselines depend on: without it
 * every reload deals a different hand of media and the reference images stop matching the page they
 * are supposed to describe.
 *
 * Each fixture owns a Randomizer over its own Mt19937 engine, seeded on first use. That isolation is
 * the whole point, and mt_srand() cannot give it: the global generator is shared with every library
 * in the process, so anything Sulu or Doctrine happens to draw between two picks shifts the sequence
 * from there on. Reloading twice on one machine hides that — the interference is identical both
 * times — while a different library version, or a branch taken only under CI, moves the media around
 * and the comparison fails for no reason the page can explain.
 *
 * The other trap: findAll() carries no ORDER BY, so the database is free to return rows in any
 * order, and a fixed seed picking from a shuffled list is still random. {@see orderById()} pins it.
 */
trait SeededRandomness
{
    private const int RANDOM_SEED = 20260813;

    /**
     * Filler prose for the pages that used to call Faker. Faker seeds itself with mt_srand(), so it
     * carried exactly the problem this trait exists to remove.
     */
    private const array PARAGRAPHS = [
        "Le lac se réveille dans une sérénité trompeuse : le vent dort encore sous les arbres de la rive et les voiles attendent, patientes. C'est à ces heures que le marin apprend la patience, cette vertu que l'eau exige avant toute autre.",
        "Rien n'est plus honnête qu'un plan d'eau : il révèle le navigateur sans les artifices du quotidien, face à lui-même et aux éléments dans toute leur souveraineté.",
        "Le vent des Ardennes a un caractère bien trempé. Né dans les forêts du massif, il dévale les pentes avec une fougue qui surprend, mais il est loyal — il ne trompe jamais qui a pris la peine de l'écouter.",
        'On ne va pas droit contre le vent. Il faut ruser, louvoyer, accepter des bords contraires pour mieux atteindre sa destination. Cette sagesse dépasse largement le cadre du lac.',
        "Chaque génération ajoute sa pierre : les Optimist d'hier sont les habitables d'aujourd'hui, et les régatiers de demain se forment ici, dans la même exigence.",
        "Quand la voile porte et que la barre répond, le bruit du monde s'efface. Il reste l'étrave, l'écume et cette plénitude que seul l'élément sait offrir.",
    ];

    private ?Randomizer $randomizer = null;

    private function randomizer(): Randomizer
    {
        return $this->randomizer ??= new Randomizer(new Mt19937(self::RANDOM_SEED));
    }

    /**
     * One key drawn from the array, the equivalent of a single-argument array_rand().
     *
     * @template TKey of array-key
     *
     * @param non-empty-array<TKey, mixed> $values
     *
     * @return TKey
     */
    private function pickKey(array $values): int|string
    {
        return $this->randomizer()->pickArrayKeys($values, 1)[0];
    }

    /**
     * @template TKey of array-key
     *
     * @param non-empty-array<TKey, mixed> $values
     *
     * @return list<TKey>
     */
    private function pickKeys(array $values, int $count): array
    {
        return $this->randomizer()->pickArrayKeys($values, $count);
    }

    /**
     * @template T
     *
     * @param array<array-key, T> $values
     *
     * @return list<T>
     */
    private function shuffled(array $values): array
    {
        return $this->randomizer()->shuffleArray($values);
    }

    private function between(int $min, int $max): int
    {
        return $this->randomizer()->getInt($min, $max);
    }

    /**
     * @return list<string>
     */
    private function paragraphs(int $count): array
    {
        $keys = $this->pickKeys(self::PARAGRAPHS, min($count, \count(self::PARAGRAPHS)));

        return array_map(static fn (int $key): string => self::PARAGRAPHS[$key], $keys);
    }

    /**
     * @template T of object
     *
     * @param array<array-key, T> $entities
     *
     * @return list<T>
     */
    private function orderById(array $entities): array
    {
        $entities = array_values($entities);
        usort(
            $entities,
            /**
             * @param T $first
             * @param T $second
             */
            static fn (object $first, object $second): int => (string) $first->getId() <=> (string) $second->getId(),
        );

        return $entities;
    }
}
