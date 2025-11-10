<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class EventsFixtures extends Fixture
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $root = $this->pageRepository->findOneBy(['parentId' => null, 'webspaceKey' => 'cvvfcm']);

        /** @var Page $events */
        $events = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $root->getWebspaceKey(),
                    $root->getId(),
                    [
                        'title' => 'Événements',
                        'url' => '/evenements',
                        'template' => 'category',
                        'locale' => 'fr',
                    ]
                ),
            ),
        );
        $events->setWebspaceKey($root->getWebspaceKey());
        $events->setParent($root);

        foreach ($events->getDimensionContents() as $eventsDimensionContent) {
            $eventsDimensionContent->addNavigationContext('main');
        }

        $manager->flush();
        $this->contentWorkflow->apply($events, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        $this->setReference('events', $events);
    }
}
