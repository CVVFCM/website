<?php

namespace App\DataFixtures;

use App\Entity\FacebookToken;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AppFixtures extends Fixture
{
    public function __construct(
        #[Autowire('env(FACEBOOK_DEFAULT_TOKEN)%')]
        private readonly string $facebookDefaultToken,
        #[Autowire('env(FACEBOOK_PAGE_TOKEN)%')]
        private readonly string $facebookPageToken,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $facebookToken = new FacebookToken(
            $this->facebookDefaultToken,
            new \DateTimeImmutable('2025-04-11 22:00:35'),
            $this->facebookPageToken,
            'Cvvfcm - Club de Voile des Vieilles Forges de Charleville-Mézières',
            '17841403547236243',
        );

        $manager->persist($facebookToken);
        $manager->flush();
    }
}
