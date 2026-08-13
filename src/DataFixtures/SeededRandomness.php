<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Faker\Factory;
use Faker\Generator;

/**
 * Makes the randomised fixtures reproducible, which the screenshot baselines depend on: without it
 * every `reset-test` deals a different hand of media and the reference images stop matching the
 * page they are supposed to describe.
 *
 * Each fixture seeds at the top of its own load(), so it is reproducible on its own whatever order
 * Doctrine runs them in — a single global seed would make the result depend on how many numbers the
 * fixtures before it happened to draw.
 *
 * Two traps this guards against:
 *  - mt_srand only governs mt_rand, array_rand and shuffle. random_int reads the CSPRNG and ignores
 *    the seed entirely, so it must not be used in a fixture.
 *  - findAll() carries no ORDER BY, so the database is free to hand rows back in any order; a fixed
 *    seed picking from a shuffled list is still random. {@see orderById()} pins that down.
 */
trait SeededRandomness
{
    private const int RANDOM_SEED = 20260813;

    private ?Generator $faker = null;

    private function seedRandomness(): void
    {
        mt_srand(self::RANDOM_SEED);
        $this->faker = null;
    }

    /**
     * Faker keeps its own generator, which mt_srand does not reach: a fresh Factory::create() per
     * call draws from a fresh random seed. One seeded instance per fixture, reused, makes the
     * generated prose reproducible.
     */
    private function faker(): Generator
    {
        if (null === $this->faker) {
            $this->faker = Factory::create();
            $this->faker->seed(self::RANDOM_SEED);
        }

        return $this->faker;
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
