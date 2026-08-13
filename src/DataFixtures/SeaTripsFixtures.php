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
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SeaTripsFixtures extends Fixture implements DependentFixtureInterface
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
        $this->seedRandomness();
        $events = $this->getReference('events', Page::class);
        $medias = $this->orderById($this->mediaRepository->findAll());
        $contacts = array_filter($this->orderById($this->contactRepository->findAll()), static fn (Contact $contact) => $contact->getMainEmail());
        $slugger = new AsciiSlugger();

        $seaTrips = [
            [
                'name' => 'Traversée du Lac des Vieilles Forges',
                'date' => 'second saturday of july next year',
                'featured' => true,
                'content_block' => [
                    'block_title' => 'Informations pratiques',
                    'block_description' => '<p>La traversée du Lac des Vieilles Forges est l\'événement phare de notre saison estivale. Ouverte à tous les niveaux, cette sortie collective traverse le lac dans sa longueur, encadrée par nos moniteurs diplômés.</p><p>Chaque équipage dispose d\'un chef de bord expérimenté. Les débutants sont les bienvenus à bord des habitables.</p>',
                    'block_services' => [
                        ['type' => 'service', 'name' => 'Gilets de sauvetage fournis', 'available' => true],
                        ['type' => 'service', 'name' => 'Encadrement par moniteur diplômé', 'available' => true],
                        ['type' => 'service', 'name' => 'Restauration à bord', 'available' => true],
                        ['type' => 'service', 'name' => 'Hébergement sur place', 'available' => false],
                    ],
                    'links_title' => 'Liens utiles',
                    'content_links' => [
                        ['type' => 'link', 'text' => 'Règlement de la traversée', 'url' => 'https://cvvfcm.fr'],
                        ['type' => 'link', 'text' => 'Plan d\'accès au lac', 'url' => 'https://cvvfcm.fr'],
                    ],
                    'cta_title' => 'Rejoignez-nous sur l\'eau',
                    'cta_text' => 'S\'inscrire à la traversée',
                    'cta_url' => 'https://cvvfcm.fr',
                ],
            ],
            ['name' => 'Sortie découverte Optimist', 'date' => 'first saturday of august next year', 'featured' => false],
            ['name' => 'Balade nautique au crépuscule', 'date' => 'third saturday of september next year', 'featured' => false],
        ];

        foreach ($seaTrips as $trip) {
            $begin = new \DateTimeImmutable($trip['date']);
            $url = '/evenements/'.$slugger->slug($trip['name'])->lower()->ascii();

            /** @var Page $seaTrip */
            $seaTrip = $this->handle(
                new Envelope(
                    new CreatePageMessage(
                        $events->getWebspaceKey(),
                        $events->getId(),
                        [
                            'title' => $trip['name'],
                            'url' => $url,
                            'template' => 'event',
                            'locale' => 'fr',
                        ]
                    ),
                ),
            );

            foreach ($seaTrip->getDimensionContents() as $dimensionContent) {
                $dimensionContent->setTemplateData(array_merge([
                    'url' => $url,
                    'title' => $trip['name'],
                    'main_media' => ['id' => $medias[array_rand($medias)]->getId()],
                    'media' => [
                        'displayOption' => null,
                        'ids' => array_map(
                            fn (int $media): int => $medias[$media]->getId(),
                            (array) array_rand($medias, mt_rand(1, 4)),
                        ),
                    ],
                    'description' => array_reduce(
                        $this->faker()->paragraphs(mt_rand(2, 4)),
                        fn (string $memo, string $paragraph): string => "$memo\n\n<p>{$paragraph}</p>",
                        '',
                    ),
                    'begin_date' => $begin->format('Y-m-d\TH:i:s'),
                    'end_date' => $begin->format('Y-m-d\TH:i:s'),
                    'featured' => $trip['featured'],
                    'event_type' => 'sea_trip',
                    'boats' => [
                        [
                            'type' => 'boat',
                            'boat_type' => 'Habitable',
                            'captain' => [$contacts[array_rand($contacts)]->getId()],
                            'available_seats' => (string) mt_rand(2, 6),
                            'approximative_price' => mt_rand(10, 30).'€',
                        ],
                        [
                            'type' => 'boat',
                            'boat_type' => 'Dériveur',
                            'captain' => [$contacts[array_rand($contacts)]->getId()],
                            'available_seats' => (string) mt_rand(1, 3),
                            'approximative_price' => mt_rand(5, 15).'€',
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
                    'contact' => ['c'.$contacts[array_rand($contacts)]->getId()],
                ], $trip['content_block'] ?? []));
            }

            $manager->flush();
            $this->contentWorkflow->apply($seaTrip, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
            $manager->flush();
        }
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            MediaFixtures::class,
            EventsFixtures::class,
            ContactsFixtures::class,
        ];
    }
}
