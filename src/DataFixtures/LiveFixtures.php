<?php

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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class LiveFixtures extends Fixture implements DependentFixtureInterface
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        private readonly PageRepositoryInterface  $pageRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        private readonly MediaRepositoryInterface $mediaRepository,
        MessageBusInterface                       $messageBus,
        #[Autowire('%env(SERVER_NAME)%')]
        private readonly string                   $serverName,
    )
    {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $medias = $this->mediaRepository->findAll();
        $root = $this->pageRepository->findOneBy(['parentId' => null, 'webspaceKey' => 'cvvfcm']);

        /** @var Page $live */
        $live = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $root->getWebspaceKey(),
                    $root->getId(),
                    [
                        'title' => 'Direct',
                        'url' => '/direct',
                        'template' => 'live',
                        'locale' => 'fr',
                    ],
                ),
            ),
        );
        $live->setWebspaceKey($root->getWebspaceKey());
        $live->setParent($root);
        $manager->persist($live);

        foreach ($live->getDimensionContents() as $liveDimensionContent) {
            /** @var PageDimensionContent $liveDimensionContent */
            $liveDimensionContent->setTemplateData([
                'title' => 'Direct',
                'url' => '/direct',
                'description' => '<p>Retrouvez le direct du lac</p>',
                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                'webcam_stream_url' => 'https://' . $this->serverName . '/stream/mouillages/channel/1/mse',
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($live, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        $this->setReference('live', $live);
    }

    public function getDependencies(): array
    {
        return [
            MediaFixtures::class,
        ];
    }
}
