<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class HomepageFixtures extends Fixture implements DependentFixtureInterface
{
    use SeededRandomness;

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
        $this->seedRandomness();
        $medias = $this->orderById($this->mediaRepository->findAll());

        $homepage = $this->pageRepository->findOneBy(['depth' => 0, 'webspaceKey' => 'cvvfcm']);
        $events = $this->getReference('events', Page::class);
        $live = $this->getReference('live', Page::class);
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
                        'header_media' => ['id' => $this->getReference('media_background_header', Media::class)->getId()],
                        'header_ctas' => [
                            [
                                'type' => 'cta',
                                'title' => 'Stage de voile',
                                'description' => "L'école de voile des Vieilles Forges, c'est le meilleur moyen pour les jeunes de découvrir et d'apprendre la voile dans les Ardennes.",
                                'image' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.cdv-ardennes.fr/stage-de-voile',
                                'settings' => [],
                            ],
                            [
                                'type' => 'cta',
                                'title' => 'Ecole de voile',
                                'description' => "L'école de voile des Vieilles Forges, c'est le meilleur moyen pour les jeunes de découvrir et d'apprendre la voile dans les Ardennes.",
                                'image' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.cdv-ardennes.fr/ecole-de-voile',
                                'settings' => [],
                            ],
                            [
                                'type' => 'cta',
                                'title' => 'Régates',
                                'description' => "L'école de voile des Vieilles Forges, c'est le meilleur moyen pour les jeunes de découvrir et d'apprendre la voile dans les Ardennes.",
                                'image' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.cdv-ardennes.fr/regates',
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
                        'featured_event' => [
                            'dataSource' => $events->getUuid(),
                            'includeSubFolders' => true,
                            'limitResult' => 1,
                        ],
                        'events' => [
                            'dataSource' => $events->getUuid(),
                            'includeSubFolders' => true,
                            'limitResult' => 5,
                        ],
                    ],
                    [
                        'type' => 'live',
                        'title' => 'En direct',
                        'description' => '<p>Avant de vous jeter à l\'eau, retrouvez les conditions météo sur le lac !</p>',
                        'webcams' => [
                            [
                                'type' => 'webcam',
                                'webcam_stream_url' => $this->serverName.'/stream/mouillages-2/channel/1/mse',
                            ],
                            [
                                'type' => 'webcam',
                                'webcam_stream_url' => $this->serverName.'/stream/mouillages/channel/1/mse',
                            ],
                        ],
                        'webcam_stream_page_link' => $live->getUuid(),
                        'links' => [
                            [
                                'type' => 'link',
                                'link_text' => 'Voir les prévisions',
                                'link_target' => $live->getUuid(),
                            ],
                            [
                                'type' => 'link',
                                'link_text' => 'Webcam',
                                'link_target' => $live->getUuid(),
                            ],
                        ],
                    ],
                    [
                        'type' => 'social',
                        'title' => 'Suivez le club',
                        'description' => '<p>Retrouvez nos actualités, nos photos, nos vidéos sur nos différentes plateformes</p>',
                    ],
                    [
                        'type' => 'partners',
                        'title' => 'Les partenaires',
                        'description' => '<p>Merci pour leur soutien et leur aide.</p>',
                        'partners' => [
                            [
                                'type' => 'partner',
                                'name' => 'Conseil Départemental des Ardennes',
                                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.ardennes.com',
                            ],
                            [
                                'type' => 'partner',
                                'name' => 'Lac des Vieilles Forges',
                                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.lacdesvieillesforges.fr',
                            ],
                            [
                                'type' => 'partner',
                                'name' => 'Région Grand Est',
                                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.grandest.fr',
                            ],
                            [
                                'type' => 'partner',
                                'name' => 'Conseil Départemental des Ardennes',
                                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.ardennes.com',
                            ],
                            [
                                'type' => 'partner',
                                'name' => 'Ville de Charleville-Mézières',
                                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.agglo-ardenne.fr',
                            ],
                            [
                                'type' => 'partner',
                                'name' => 'Lac des Vieilles Forges',
                                'media' => ['id' => $medias[array_rand($medias)]->getId()],
                                'url' => 'https://www.lacdesvieillesforges.fr',
                            ],
                        ],
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
            EventsFixtures::class,
            LiveFixtures::class,
            MediaFixtures::class,
        ];
    }
}
