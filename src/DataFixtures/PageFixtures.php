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
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class PageFixtures extends Fixture implements DependentFixtureInterface
{
    use SeededRandomness;

    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        private readonly MediaRepositoryInterface $mediaRepository,
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
                        'title' => 'Adhérer',
                        'url' => '/adherer',
                        'template' => 'default',
                        'locale' => 'fr',
                    ]
                ),
            ),
        );
        $events->setWebspaceKey($root->getWebspaceKey());
        $events->setParent($root);

        foreach ($events->getDimensionContents() as $eventsDimensionContent) {
            $eventsDimensionContent->setTemplateData([
                'title' => 'Adhérer',
                'url' => '/adherer',
                'article' => '<p>Adhérez au CVVFCM</p>',
                'media' => ['id' => $medias[$this->pickKey($medias)]->getId()],
                'form' => 1,
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($events, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [MediaFixtures::class];
    }
}
