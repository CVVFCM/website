<?php

namespace App\DataFixtures;

use App\Entity\FacebookToken;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $facebookToken = new FacebookToken(
            'EAAHfYAZBxApgBO0RvrIVauYcWbjCZAPUw7rm7cRHe4iU3oQMsH8UoH3VkP1NU0LfptWBHTtvTEqbt51FyoYqImkfSm7dgYfFwAnJPZApt2KfrDp31qDq5RulyXtf3RO69M34Ken0QEdAZCrZC8suACphkamRhSAtv3m31BNhTC4UuWEJZCH8dBvwZDZD',
            new \DateTimeImmutable('2025-04-11 22:00:35'),
            'EAAHfYAZBxApgBO1ELFWQqCyP2u2fCzrpFFfJ49tqQs1hAJQKNe2FKGvZAMOsZCXWmWFlsc1a6hGKUuJRD2SjBBRinVAwuaG1yZAPuoSkhZAqqWLhx59W0WDijONOuDyMmFTbMVGbFpZBsJXy4qzZA5zzAbTwdGWXC2SuoVPhBzFED73m0hYqorikNdXEGvyMwZDZD',
            'Cvvfcm - Club de Voile des Vieilles Forges de Charleville-Mézières',
            '17841403547236243',
        );

        $manager->persist($facebookToken);
        $manager->flush();
    }
}
