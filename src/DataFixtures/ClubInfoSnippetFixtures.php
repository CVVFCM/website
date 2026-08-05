<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionMeta;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Model\Page;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Application\Message\CreateSnippetMessage;
use Sulu\Snippet\Application\Message\ModifySnippetAreaMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class ClubInfoSnippetFixtures extends Fixture implements DependentFixtureInterface
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly MediaManagerInterface $mediaManager,
    ) {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $labelsCollection = new Collection();
        $labelsCollection->setType($manager->find(CollectionType::class, 1));
        $manager->persist($labelsCollection);

        $labelsCollectionMeta = new CollectionMeta();
        $labelsCollectionMeta->setLocale('fr');
        $labelsCollectionMeta->setTitle('Labels / Certifications');
        $labelsCollectionMeta->setCollection($labelsCollection);
        $manager->persist($labelsCollectionMeta);
        $manager->flush();

        $labelMedia = [];
        $finder = Finder::create()->in(__DIR__.'/stubs/labels')->files()->depth(0)->sortByName();
        foreach ($finder as $fileInfo) {
            $media = $this->mediaManager->save(
                new UploadedFile($fileInfo->getPathname(), $fileInfo->getFilename()),
                ['locale' => 'fr', 'collection' => $labelsCollection->getId()],
                1,
            );
            $labelMedia[$fileInfo->getFilenameWithoutExtension()] = $manager->find(Media::class, $media->getId());
        }

        $events = $this->getReference('events', Page::class);
        $live = $this->getReference('live', Page::class);
        $regattas = $this->getReference('regattas', Page::class);

        /** @var SnippetInterface $snippet */
        $snippet = $this->handle(
            new Envelope(
                new CreateSnippetMessage([
                    'locale' => 'fr',
                    'template' => 'club_info',
                    'title' => 'Informations du club',
                    'address' => '5 rue du Lac – 08500 Les Mazures',
                    'legal_status' => 'Association sportive loi 1901',
                    'siret' => '123 123 123 123456',
                    'email' => 'contact@cvvfcm.fr',
                    'location' => [
                        'code' => '08500',
                        'country' => 'FR',
                        'lat' => 49.87332855,
                        'long' => 4.59566473,
                        'number' => null,
                        'street' => null,
                        'title' => 'CVVFCM',
                        'town' => 'Les Mazures',
                        'zoom' => 15,
                    ],
                    'labels' => [
                        [
                            'type' => 'label',
                            'name' => 'EFVoile',
                            'logo' => ['id' => $labelMedia['efvoile']->getId()],
                            'url' => 'https://www.efvoile.fr',
                        ],
                        [
                            'type' => 'label',
                            'name' => 'FFVoile',
                            'logo' => ['id' => $labelMedia['ffvoile']->getId()],
                            'url' => 'https://www.ffvoile.fr',
                        ],
                    ],
                    'legal_links' => [
                        [
                            'type' => 'legal',
                            'label' => 'Mentions légales',
                            'page' => $events->getUuid(),
                        ],
                    ],
                    'col1_title' => 'Activités',
                    'col1_links' => [
                        [
                            'type' => 'link',
                            'label' => 'Événements',
                            'page' => $events->getUuid(),
                        ],
                        [
                            'type' => 'link',
                            'label' => 'En direct',
                            'page' => $live->getUuid(),
                        ],
                    ],
                    'col2_title' => 'Le Club',
                    'col2_links' => [
                        [
                            'type' => 'link',
                            'label' => 'Régates',
                            'page' => $regattas->getUuid(),
                        ],
                    ],
                ]),
            ),
        );

        $manager->flush();

        $this->handle(
            new Envelope(
                new ApplyWorkflowTransitionSnippetMessage(
                    ['uuid' => $snippet->getUuid()],
                    'fr',
                    WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
            ),
        );

        $manager->flush();

        $this->handle(
            new Envelope(
                new ModifySnippetAreaMessage([
                    'webspaceKey' => 'cvvfcm',
                    'areaKey' => 'club_info',
                    'snippetIdentifier' => ['uuid' => $snippet->getUuid()],
                    'locale' => 'fr',
                ]),
            ),
        );

        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            EventsFixtures::class,
            LiveFixtures::class,
            RegattasFixtures::class,
        ];
    }
}
