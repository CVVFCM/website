<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\ContactRepositoryInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class RegattasFixtures extends Fixture implements DependentFixtureInterface
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $events = $this->getReference('events', Page::class);
        $regattas = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $events->getWebspaceKey(),
                    $events->getId(),
                    [
                        'title' => 'Régates',
                        'url' => '/evenements/regates',
                        'template' => 'category',
                        'locale' => 'fr',
                        'stage' => DimensionContentInterface::STAGE_LIVE,
                    ]
                ),
            ),
        );
        $regattas->setParent($events);
        $manager->persist($regattas);

        foreach ($regattas->getDimensionContents() as $regattasDimensionContent) {
            $regattasDimensionContent->addNavigationContext('main');
        }

        $manager->flush();
        $this->contentWorkflow->apply($regattas, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();
        $this->setReference('regattas', $regattas);

        $this->createRegatta($manager, 'Trophée du Coeur de l\'Europe', new \DateTimeImmutable('third saturday of june next year'));
        $this->createRegatta($manager, 'Coupe Bernard Bozier', new \DateTimeImmutable('first saturday of may next year'));
        $this->createRegatta($manager, 'Tour des Lacs Yole OK', new \DateTimeImmutable('third saturday of may next year'));
        $this->createRegatta($manager, 'Femmes à la Barre - L\'Ardennaise', new \DateTimeImmutable('third saturday of july next year'));
        $this->createRegatta($manager, 'National Maraudeur', new \DateTimeImmutable('first saturday of june next year'));

        $manager->flush();
    }

    private function createRegatta(ObjectManager $manager, string $name, \DateTimeImmutable $begin): void
    {
        $regattas = $this->getReference('regattas', Page::class);

        $medias = $this->mediaRepository->findAll();
        $contacts = array_filter($this->contactRepository->findAll(), static fn (Contact $contact) => $contact->getMainEmail());

        /** @var Page $regatta */
        $regatta = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $regattas->getWebspaceKey(),
                    $regattas->getId(),
                    [
                        'title' => $name,
                        'url' => '/evenements/regates/'.(new AsciiSlugger())->slug($name)->lower()->ascii(),
                        'template' => 'category',
                        'locale' => 'fr',
                        'stage' => DimensionContentInterface::STAGE_LIVE,
                    ]
                ),
            ),
        );

        foreach ($regatta->getDimensionContents() as $regattaDimensionContent) {
            $regattaDimensionContent->setWorkflowPlace(WorkflowInterface::WORKFLOW_PLACE_PUBLISHED);
        }

        $manager->flush();
        $this->contentWorkflow->apply($regatta, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        for ($i = 0; $i < 3; ++$i) {
            $editionName = $name.' '.$begin->format('Y');
            $url = $regatta->getDimensionContents()->last()->getTemplateData()['url'].'/'.(new AsciiSlugger())->slug($editionName)->lower()->ascii();

            /** @var Page $edition */
            $editionData = [
                'url' => $url,
                'title' => $editionName,
                'template' => 'event',
                'locale' => 'fr',
            ];
            $edition = $this->handle(
                new Envelope(new CreatePageMessage($regatta->getWebspaceKey(), $regatta->getId(), $editionData)),
            );

            foreach ($edition->getDimensionContents() as $editionDimensionContent) {
                $editionDimensionContent->setTemplateData([
                    'url' => $url,
                    'title' => $editionName,
                    'main_media' => ['id' => $medias[array_rand($medias)]->getId()],
                    'media' => [
                        'displayOption' => null,
                        'ids' => array_map(
                            fn (int $media): int => $medias[$media]->getId(),
                            (array) array_rand($medias, random_int(1, 4)),
                        ),
                    ],
                    'description' => array_reduce(
                        Factory::create()->paragraphs(random_int(2, 4)),
                        fn (string $memo, string $paragraph): string => "$memo\n\n<p>{$paragraph}</p>",
                        '',
                    ),
                    'begin_date' => $begin->format('Y-m-d\TH:i:s'),
                    'end_date' => $begin->modify('+1 day')->format('Y-m-d\TH:i:s'),
                    'event_type' => 'regatta',
                    'series' => [
                        [
                            'type' => 'series_with_rank',
                            'series' => 'Yole OK',
                            'rank' => '5B',
                        ],
                        [
                            'type' => 'series_with_rank',
                            'series' => 'OSIRIS',
                            'rank' => '5A',
                        ],
                    ],
                    'contact' => ['c'.$contacts[array_rand($contacts)]->getId()],
                    'links' => [
                        [
                            'type' => 'link',
                            'title' => 'Tableau Officiel',
                            'url' => 'https://drive.google.com/drive/folders/1-DxB5kPmqgkFx4bJF-l-gUeJAWjOHkyq?usp=sharing',
                        ],
                        [
                            'type' => 'link',
                            'title' => 'CVVFCM',
                            'url' => 'https://cvvfcm.fr',
                        ],
                    ],
                    'location' => [
                        'code' => '08500',
                        'country' => 'FR',
                        'lat' => 49.87332855,
                        'long' => 4.59566473,
                        'number' => null,
                        'street' => null,
                        'title' => 'CVVFCM',
                        'town' => 'Les Mazures',
                        'zoom' => 17,
                    ],
                    'regatta_informations' => array_reduce(
                        Factory::create()->paragraphs(random_int(2, 4)),
                        fn (string $memo, string $paragraph): string => "$memo\n\n<p>{$paragraph}</p>",
                        '',
                    ),
                    'services' => [
                        ['type' => 'service', 'name' => 'Buvette', 'availability' => true],
                        ['type' => 'service', 'name' => 'Petite restauration', 'availability' => false],
                        ['type' => 'service', 'name' => 'Toilettes', 'availability' => true],
                        ['type' => 'service', 'name' => 'Posibilité de camper', 'availability' => true],
                    ],
                ]);
            }

            $manager->flush();
            $this->contentWorkflow->apply($edition, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
            $manager->flush();

            $begin = $begin->modify('-1 year');
        }
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            MediaFixtures::class,
            EventsFixtures::class,
        ];
    }
}
