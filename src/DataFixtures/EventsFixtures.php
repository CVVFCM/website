<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class EventsFixtures extends Fixture implements DependentFixtureInterface
{
    use SeededRandomness;

    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $root = $this->pageRepository->findOneBy(['parentId' => null, 'webspaceKey' => 'cvvfcm']);
        $medias = $this->orderById($this->mediaRepository->findAll());

        /** @var Page $events */
        $events = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $root->getWebspaceKey(),
                    $root->getId(),
                    [
                        'title' => 'Événements',
                        'url' => '/evenements',
                        'template' => 'list',
                        'locale' => 'fr',
                    ]
                ),
            ),
        );
        $events->setWebspaceKey($root->getWebspaceKey());
        $events->setParent($root);

        foreach ($events->getDimensionContents() as $eventsDimensionContent) {
            /* @var PageDimensionContent $eventsDimensionContent */
            $eventsDimensionContent->setTemplateData([
                'title' => 'Événements',
                'url' => '/evenements',
                'description' => '<p>Retrouvez tous les événements à venir du Club de Voile des Vieilles Forges de Charleville-Mézières.</p>',
                'media' => ['id' => $medias[$this->pickKey($medias)]->getId()],
                'list_type' => 'event',
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($events, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        // L'UUID est disponible après le flush — on configure le datasource du smart_content
        foreach ($events->getDimensionContents() as $eventsDimensionContent) {
            /* @var PageDimensionContent $eventsDimensionContent */
            $data = $eventsDimensionContent->getTemplateData();
            $data['event_list'] = [
                'dataSource' => $events->getId(),
                'includeSubFolders' => true,
                'tags' => [],
                'tagOperator' => 'or',
                'categories' => [],
                'categoryOperator' => 'and',
            ];
            $eventsDimensionContent->setTemplateData($data);
        }
        $manager->flush();

        $this->setReference('events', $events);
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            MediaFixtures::class,
        ];
    }
}
