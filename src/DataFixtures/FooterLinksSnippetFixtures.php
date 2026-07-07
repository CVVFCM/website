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

final class FooterLinksSnippetFixtures extends Fixture implements DependentFixtureInterface
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
        $contact = $this->getReference('contact', Page::class);
        $pictoContact = $this->getReference('picto_contact', Media::class);
        $pictoFacebook = $this->getReference('picto_facebook', Media::class);
        $pictoX = $this->getReference('picto_x', Media::class);
        $pictoInsta = $this->getReference('picto_insta', Media::class);
        $pictoYoutube = $this->getReference('picto_youtube', Media::class);

        $this->createAndPublishArea(
            $manager,
            'Footer — Contact',
            'footer_contact',
            [
                [
                    'type' => 'internal',
                    'label' => 'Contact',
                    'page' => $contact->getUuid(),
                    'media' => ['id' => $pictoContact->getId()],
                    'display' => ['button'],
                ],
            ],
        );

        $this->createAndPublishArea(
            $manager,
            'Footer — Réseaux sociaux',
            'footer_social',
            [
                [
                    'type' => 'external',
                    'label' => 'Facebook',
                    'url' => 'https://www.facebook.com/cvvfcm',
                    'media' => ['id' => $pictoFacebook->getId()],
                    'display' => ['hidden_text'],
                ],
                [
                    'type' => 'external',
                    'label' => 'X / Twitter',
                    'url' => 'https://x.com/cvvfcm',
                    'media' => ['id' => $pictoX->getId()],
                    'display' => ['hidden_text'],
                ],
                [
                    'type' => 'external',
                    'label' => 'Instagram',
                    'url' => 'https://www.instagram.com/cvvfcm',
                    'media' => ['id' => $pictoInsta->getId()],
                    'display' => ['hidden_text'],
                ],
                [
                    'type' => 'external',
                    'label' => 'YouTube',
                    'url' => 'https://www.youtube.com/@cvvfcm',
                    'media' => ['id' => $pictoYoutube->getId()],
                    'display' => ['hidden_text'],
                ],
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $links
     */
    private function createAndPublishArea(ObjectManager $manager, string $title, string $areaKey, array $links): void
    {
        /** @var SnippetInterface $snippet */
        $snippet = $this->handle(
            new Envelope(
                new CreateSnippetMessage([
                    'locale' => 'fr',
                    'template' => 'navigation_links',
                    'title' => $title,
                    'links' => $links,
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
                    'areaKey' => $areaKey,
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
        return [ContactFixtures::class, NavigationLinksSnippetFixtures::class];
    }
}
