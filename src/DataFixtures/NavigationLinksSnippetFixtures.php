<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Model\Page;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Application\Message\CreateSnippetMessage;
use Sulu\Snippet\Application\Message\ModifySnippetAreaMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class NavigationLinksSnippetFixtures extends Fixture implements DependentFixtureInterface
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $homepage = $this->getReference('homepage', Page::class);
        $events = $this->getReference('events', Page::class);
        $live = $this->getReference('live', Page::class);
        $regattas = $this->getReference('regattas', Page::class);

        $pictoEvent = $this->getReference('media_picto_event', Media::class);
        $pictoLive = $this->getReference('media_picto_live', Media::class);
        $pictoMember = $this->getReference('media_picto_member', Media::class);
        $pictoBoutique = $this->getReference('media_picto_boutique', Media::class);

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
                        ],
                        [
                            'type' => 'internal',
                            'label' => 'Régates',
                            'page' => $regattas->getUuid(),
                            'media' => ['id' => $pictoMember->getId()],
                        ],
                        [
                            'type' => 'internal',
                            'label' => 'En direct',
                            'page' => $live->getUuid(),
                            'media' => ['id' => $pictoLive->getId()],
                        ],
                        [
                            'type' => 'external',
                            'label' => 'Boutique',
                            'url' => 'https://boutique.cvvfcm.fr',
                            'media' => ['id' => $pictoBoutique->getId()],
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
            HomepageFixtures::class,
            EventsFixtures::class,
            LiveFixtures::class,
            RegattasFixtures::class,
            MediaFixtures::class,
        ];
    }
}
