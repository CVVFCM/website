<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class HomepageFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        #[Autowire('%env(SERVER_NAME)%')]
        private readonly string $serverName,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $medias = $this->mediaRepository->findAll();

        $homepage = $this->pageRepository->findOneBy(['depth' => 0, 'webspaceKey' => 'cvvfcm']);
        $events = $this->getReference('events', Page::class);
        foreach ($homepage->getDimensionContents() as $homepageDimensionContent) {
            /** @var PageDimensionContent $homepageDimensionContent */
            if (!$homepageDimensionContent->getTitle()) {
                continue;
            }

            $homepageDimensionContent->addNavigationContext('main');
            $homepageDimensionContent->addAvailableLocale('fr');
            $homepageDimensionContent->setLocale('fr');
            $homepageDimensionContent->setTemplateKey('homepage');
            $homepageDimensionContent->setWorkflowPlace(WorkflowInterface::WORKFLOW_PLACE_PUBLISHED);
            $homepageDimensionContent->setTemplateData([
                ...$homepageDimensionContent->getTemplateData(),
                'content' => [
                    [
                        'type' => 'header',
                        'header_media' => ['id' => $medias[array_rand($medias)]->getId()],
                        'header_ctas' => [
                            [
                                'type' => 'cta',
                                'title' => 'Adhérez en ligne',
                                'url' => 'https://www.helloasso.com/associations/cvvfcm/adhesions/adhesion-2025-en-ligne-club-de-voile-des-vieilles-forges-de-charleville',
                                'settings' => [],
                            ],
                            [
                                'type' => 'cta',
                                'title' => 'Passer le permis bateau',
                                'url' => 'https://www.cdv-ardennes.fr/se-former/permis-bateau',
                                'settings' => [],
                            ],
                        ],
                    ],
                    [
                        'type' => 'calendar',
                        'title' => 'Calendrier',
                        'description' => '<p>Retrouvez nous tout au long de l\'année</p>',
                        'link_text' => 'Tous les événements',
                        'link_target' => $events->getUuid(),
                        'events' => [
                            'dataSource' => $events->getUuid(),
                            'includeSubFolders' => true,
                            'limitResult' => 3,
                        ],
                    ],
                    [
                        'type' => 'live',
                        'title' => 'En direct',
                        'description' => '<p>Avant de vous jeter à l\'eau, retrouvez les conditions météo sur le lac !</p>',
                        'webcam_stream_url' => 'http://'.$this->serverName.':8083/stream/mouillages/channel/0/webrtc',
                        'links' => [
                            [
                                'type' => 'link',
                                'link_text' => 'Voir les prévisions',
                                'link_target' => $events->getUuid(),
                            ],
                            [
                                'type' => 'link',
                                'link_text' => 'Webcam',
                                'link_target' => $events->getUuid(),
                            ],
                        ],
                    ],
                    [
                        'type' => 'social',
                        'title' => 'Suivez le club',
                        'description' => '<p>Retrouvez nos actualités, nos photos, nos vidéos sur nos différentes plateformes</p>',
                    ],
                ],
            ]);
        }

        $manager->flush();

        $this->setReference('homepage', $homepage);
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
