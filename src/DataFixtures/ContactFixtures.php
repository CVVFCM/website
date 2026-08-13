<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\FormBundle\Entity\Form;
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

final class ContactFixtures extends Fixture implements DependentFixtureInterface
{
    use SeededRandomness;

    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        private readonly MediaRepositoryInterface $mediaRepository,
        MessageBusInterface $messageBus,
        #[Autowire('%env(SERVER_NAME)%')]
        private readonly string $serverName,
    ) {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $medias = $this->orderById($this->mediaRepository->findAll());
        $root = $this->pageRepository->findOneBy(['parentId' => null, 'webspaceKey' => 'cvvfcm']);

        /** @var Page $contact */
        $contact = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $root->getWebspaceKey(),
                    $root->getId(),
                    [
                        'title' => 'Contact',
                        'url' => '/contact',
                        'template' => 'default',
                        'locale' => 'fr',
                    ],
                ),
            ),
        );
        $contact->setWebspaceKey($root->getWebspaceKey());
        $contact->setParent($root);
        $manager->persist($contact);

        foreach ($contact->getDimensionContents() as $contactDimensionContent) {
            /* @var PageDimensionContent $contactDimensionContent */
            $contactDimensionContent->addNavigationContext('footer');
            $contactDimensionContent->setTemplateData([
                'title' => 'Contact',
                'url' => '/contact',
                'description' => '<p>Contactez le CVVFCM</p>',
                'media' => ['id' => $medias[$this->pickKey($medias)]->getId()],
                'form' => $this->getReference(ContactFormFixtures::CONTACT_FORM_REFERENCE, Form::class)->getId(),
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($contact, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        $this->setReference('contact', $contact);
    }

    public function getDependencies(): array
    {
        return [
            MediaFixtures::class,
            ContactFormFixtures::class,
        ];
    }
}
