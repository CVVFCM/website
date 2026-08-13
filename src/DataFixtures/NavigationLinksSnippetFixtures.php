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

final class NavigationLinksSnippetFixtures extends Fixture implements DependentFixtureInterface
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
        $pictosCollection = new Collection();
        $pictosCollection->setType($manager->find(CollectionType::class, 1));
        $manager->persist($pictosCollection);

        $pictosCollectionMeta = new CollectionMeta();
        $pictosCollectionMeta->setLocale('fr');
        $pictosCollectionMeta->setTitle('Pictogrammes navigation');
        $pictosCollectionMeta->setDescription('Pictogrammes utilisés dans les liens de navigation');
        $pictosCollectionMeta->setCollection($pictosCollection);
        $manager->persist($pictosCollectionMeta);
        $manager->flush();

        $pictos = [];
        $finder = Finder::create()->in(__DIR__.'/stubs/pictos')->files()->depth(0)->sortByName();
        foreach ($finder as $fileInfo) {
            $media = $this->mediaManager->save(
                new UploadedFile($fileInfo->getPathname(), $fileInfo->getFilename()),
                ['locale' => 'fr', 'collection' => $pictosCollection->getId()],
                1,
            );
            $entity = $manager->find(Media::class, $media->getId());
            $pictos[$fileInfo->getFilenameWithoutExtension()] = $entity;
            $this->addReference('picto_'.$fileInfo->getFilenameWithoutExtension(), $entity);
        }

        $events = $this->getReference('events', Page::class);
        $live = $this->getReference('live', Page::class);
        $regattas = $this->getReference('regattas', Page::class);

        $pictoEvent = $pictos['event'];
        $pictoLive = $pictos['live'];
        $pictoMember = $pictos['member'];
        $pictoBoutique = $pictos['boutique'];

        /** @var SnippetInterface $snippet */
        $snippet = $this->handle(
            new Envelope(
                new CreateSnippetMessage([
                    'locale' => 'fr',
                    'template' => 'navigation_links',
                    'title' => 'Liens de navigation',
                    'links' => [
                        [
                            'type' => 'internal',
                            'label' => 'Événements',
                            'page' => $events->getUuid(),
                            'media' => ['id' => $pictoEvent->getId()],
                            'display' => ['header_right'],
                        ],
                        [
                            'type' => 'internal',
                            'label' => 'Adhérer',
                            'page' => $regattas->getUuid(),
                            'media' => ['id' => $pictoMember->getId()],
                            'display' => ['button', 'header_top'],
                        ],
                        [
                            'type' => 'internal',
                            'label' => 'En direct',
                            'page' => $live->getUuid(),
                            'media' => ['id' => $pictoLive->getId()],
                            'display' => ['header_right'],
                        ],
                        [
                            'type' => 'external',
                            'label' => 'Boutique',
                            'url' => 'https://boutique.cvvfcm.fr',
                            'media' => ['id' => $pictoBoutique->getId()],
                            'display' => ['hidden_text', 'header_top'],
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
                    'areaKey' => 'navigation_links',
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
