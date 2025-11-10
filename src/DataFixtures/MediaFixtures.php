<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadCollectionTypes;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionMeta;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly MediaManagerInterface $mediaManager)
    {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $stubsCollection = new Collection();
        $stubsCollection->setType($manager->find(CollectionType::class, 1));
        $manager->persist($stubsCollection);

        $stubsCollectionMeta = new CollectionMeta();
        $stubsCollectionMeta->setLocale('fr');
        $stubsCollectionMeta->setTitle('Stubs');
        $stubsCollectionMeta->setDescription('Média de test divers');
        $stubsCollectionMeta->setCollection($stubsCollection);
        $manager->persist($stubsCollectionMeta);
        $manager->flush();

        $finder = Finder::create()->in(__DIR__.'/stubs')->files()->depth(0);
        foreach ($finder as $fileInfo) {
            $this->mediaManager->save(
                new UploadedFile($fileInfo->getPathname(), $fileInfo->getFilename()),
                ['locale' => 'fr', 'collection' => $stubsCollection->getId()],
                1,
            );
        }

        $partnerCollection = new Collection();
        $partnerCollection->setType($manager->find(CollectionType::class, 1));
        $manager->persist($partnerCollection);

        $partnerCollectionMeta = new CollectionMeta();
        $partnerCollectionMeta->setLocale('fr');
        $partnerCollectionMeta->setTitle('Logos / Partenaires');
        $partnerCollectionMeta->setDescription('Logos et visuels partenaires');
        $partnerCollectionMeta->setCollection($partnerCollection);
        $manager->persist($partnerCollectionMeta);
        $manager->flush();

        $finder = Finder::create()->in(__DIR__.'/stubs/partner')->files()->depth(0);
        foreach ($finder as $fileInfo) {
            $this->mediaManager->save(
                new UploadedFile($fileInfo->getPathname(), $fileInfo->getFilename()),
                ['locale' => 'fr', 'collection' => $partnerCollection->getId()],
                1,
            );
        }
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [LoadCollectionTypes::class];
    }
}
