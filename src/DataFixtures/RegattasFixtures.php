<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
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
    use SeededRandomness;

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
        $medias = $this->orderById($this->mediaRepository->findAll());
        $events = $this->getReference('events', Page::class);
        $regattas = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $events->getWebspaceKey(),
                    $events->getId(),
                    [
                        'title' => 'Régates',
                        'url' => '/evenements/regates',
                        'template' => 'list',
                        'locale' => 'fr',
                        'stage' => DimensionContentInterface::STAGE_LIVE,
                    ]
                ),
            ),
        );
        $regattas->setParent($events);
        $manager->persist($regattas);

        foreach ($regattas->getDimensionContents() as $regattasDimensionContent) {
            $regattasDimensionContent->setTemplateData([
                'url' => '/evenements/regates',
                'title' => 'Régates',
                'description' => 'Retrouvez ici toutes les régates du CVVFCM.',
                'media' => ['id' => $medias[$this->pickKey($medias)]->getId()],
                'list_type' => 'page',
                'page_list' => [
                    'dataSource' => $regattas->getUuid(),
                    'includeSubFolders' => false,
                ],
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($regattas, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();
        $this->setReference('regattas', $regattas);

        $this->createRegatta($manager, 'Trophée du Coeur de l\'Europe', new \DateTimeImmutable('third saturday of june next year'), featured: true);
        $this->createRegatta($manager, 'Coupe Bernard Bozier', new \DateTimeImmutable('first saturday of may next year'));
        $this->createRegatta($manager, 'Tour des Lacs Yole OK', new \DateTimeImmutable('third saturday of may next year'));
        $this->createRegatta($manager, 'Femmes à la Barre - L\'Ardennaise', new \DateTimeImmutable('third saturday of july next year'));
        $this->createRegatta($manager, 'National Maraudeur', new \DateTimeImmutable('first saturday of june next year'));

        $manager->flush();
    }

    private function createRegatta(ObjectManager $manager, string $name, \DateTimeImmutable $begin, bool $featured = false): void
    {
        $regattas = $this->getReference('regattas', Page::class);

        $medias = $this->orderById($this->mediaRepository->findAll());
        $contacts = array_filter($this->orderById($this->contactRepository->findAll()), static fn (Contact $contact) => $contact->getMainEmail());

        /** @var Page $regatta */
        $regatta = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $regattas->getWebspaceKey(),
                    $regattas->getId(),
                    [
                        'title' => $name,
                        'url' => '/evenements/regates/'.(new AsciiSlugger())->slug($name)->lower()->ascii(),
                        'template' => 'list',
                        'locale' => 'fr',
                        'stage' => DimensionContentInterface::STAGE_LIVE,
                    ]
                ),
            ),
        );

        foreach ($regatta->getDimensionContents() as $regattaDimensionContent) {
            $regattaDimensionContent->setWorkflowPlace(WorkflowInterface::WORKFLOW_PLACE_PUBLISHED);
            $regattaDimensionContent->setTemplateData([
                'url' => '/evenements/regates/'.(new AsciiSlugger())->slug($name)->lower()->ascii(),
                'title' => $name,
                'description' => 'Retrouvez ici toutes les éditions de la régate '.$name.'.',
                'media' => ['id' => $medias[$this->pickKey($medias)]->getId()],
                'list_type' => 'page',
                'page_list' => [
                    'dataSource' => $regatta->getUuid(),
                    'includeSubFolders' => false,
                ],
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($regatta, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        for ($i = 0; $i < 3; ++$i) {
            $editionName = $name.' '.$begin->format('Y');
            $url = $regatta->getDimensionContents()->last()->getRoute()->getSlug().'/'.(new AsciiSlugger())->slug($editionName)->lower()->ascii();

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
                    'featured' => $featured && 0 === $i,
                    'main_media' => ['id' => $medias[$this->pickKey($medias)]->getId()],
                    'media' => [
                        'displayOption' => null,
                        'ids' => array_map(
                            fn (int $media): int => $medias[$media]->getId(),
                            $this->pickKeys($medias, $this->between(1, 4)),
                        ),
                    ],
                    'description' => array_reduce(
                        $this->paragraphs($this->between(2, 4)),
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
                    'series_button_title' => 'Classement',
                    'series_button_text' => 'Résultats',
                    'series_button_url' => 'https://cvvfcm.fr',
                    'series_links' => [
                        ['type' => 'link', 'text' => 'Tableau officiel', 'url' => 'https://drive.google.com/drive/folders/1-DxB5kPmqgkFx4bJF-l-gUeJAWjOHkyq?usp=sharing'],
                    ],
                    'contact' => ['c'.$contacts[$this->pickKey($contacts)]->getId()],
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
                        $this->paragraphs($this->between(2, 4)),
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
