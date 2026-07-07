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

/**
 * Pages événements "Full" — une par type d'événement, avec l'intégralité des
 * champs de saisie renseignés (blocs de texte de 20 à 30 mots, listes de 3 à
 * 8 éléments), pour vérifier visuellement le rendu complet du template event.
 */
final class EventFullContentFixtures extends Fixture implements DependentFixtureInterface
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    private const LOCATION = [
        'code' => '08500',
        'country' => 'FR',
        'lat' => 49.87332855,
        'long' => 4.59566473,
        'number' => null,
        'street' => null,
        'title' => 'CVVFCM',
        'town' => 'Les Mazures',
        'zoom' => 17,
    ];

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
        $medias = $this->mediaRepository->findAll();
        $contacts = array_values(array_filter(
            $this->contactRepository->findAll(),
            static fn (Contact $contact) => $contact->getMainEmail(),
        ));
        $slugger = new AsciiSlugger();

        $this->createFullEvent($manager, $events, $medias, $contacts, $slugger, 'Événement Full', 'default');
        $this->createFullEvent($manager, $events, $medias, $contacts, $slugger, 'Régate Full', 'regatta');
        $this->createFullEvent($manager, $events, $medias, $contacts, $slugger, 'Sortie en mer Full', 'sea_trip');
    }

    /**
     * @param list<\Sulu\Bundle\MediaBundle\Entity\Media> $medias
     * @param list<Contact>                               $contacts
     */
    private function createFullEvent(
        ObjectManager $manager,
        Page $events,
        array $medias,
        array $contacts,
        AsciiSlugger $slugger,
        string $title,
        string $eventType,
    ): void {
        $url = '/evenements/'.$slugger->slug($title)->lower()->ascii();
        $begin = new \DateTimeImmutable('next saturday 10:00');
        $contactId = $contacts[array_rand($contacts)]->getId();

        /** @var Page $page */
        $page = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $events->getWebspaceKey(),
                    $events->getId(),
                    [
                        'title' => $title,
                        'url' => $url,
                        'template' => 'event',
                        'locale' => 'fr',
                    ]
                ),
            ),
        );

        $data = [
            'url' => $url,
            'title' => $title,
            'featured' => false,
            'event_type' => $eventType,
            'main_media' => ['id' => $medias[array_rand($medias)]->getId()],
            'media' => [
                'displayOption' => null,
                'ids' => array_map(
                    fn (int $index): int => $medias[$index]->getId(),
                    (array) array_rand($medias, min(4, \count($medias))),
                ),
            ],
            'description' => '<p>Rejoignez le club pour cet événement convivial ouvert à tous les niveaux, encadré par nos moniteurs diplômés d\'État, dans une ambiance chaleureuse et conviviale.</p>',
            'begin_date' => $begin->format('Y-m-d\TH:i:s'),
            'end_date' => $begin->modify('+1 day')->format('Y-m-d\TH:i:s'),
            'location' => self::LOCATION,
            'contact' => ['c'.$contactId],
            'links' => [
                ['type' => 'link', 'title' => 'Règlement complet', 'url' => 'https://cvvfcm.fr', 'target_blank' => true],
                ['type' => 'link', 'title' => 'Plan d\'accès au club', 'url' => 'https://cvvfcm.fr', 'target_blank' => false],
                ['type' => 'link', 'title' => 'Formulaire d\'inscription', 'url' => 'https://cvvfcm.fr', 'target_blank' => true],
                ['type' => 'link', 'title' => 'Charte du navigateur', 'url' => 'https://cvvfcm.fr', 'target_blank' => false],
            ],
            'block_title' => 'Informations pratiques',
            'block_description' => '<p>Cette section rassemble toutes les informations nécessaires à votre venue : horaires d\'accueil, matériel fourni par le club, consignes de sécurité et modalités d\'inscription en ligne.</p>',
            'block_services' => [
                ['type' => 'service', 'name' => 'Accueil dès 8h', 'available' => true],
                ['type' => 'service', 'name' => 'Prêt de matériel', 'available' => true],
                ['type' => 'service', 'name' => 'Vestiaires et douches', 'available' => true],
                ['type' => 'service', 'name' => 'Encadrement diplômé', 'available' => true],
                ['type' => 'service', 'name' => 'Restauration sur place', 'available' => false],
            ],
            'links_title' => 'Pour aller plus loin',
            'content_links' => [
                ['type' => 'link', 'text' => 'Tarifs et formules', 'url' => 'https://cvvfcm.fr'],
                ['type' => 'link', 'text' => 'Contacter le club', 'url' => 'https://cvvfcm.fr'],
                ['type' => 'link', 'text' => 'Galerie photo', 'url' => 'https://cvvfcm.fr'],
            ],
            'cta_title' => 'Rejoignez-nous sur l\'eau',
            'cta_text' => 'S\'inscrire à l\'événement',
            'cta_url' => 'https://cvvfcm.fr',
        ];

        if ('regatta' === $eventType) {
            $data['series'] = [
                ['type' => 'series_with_rank', 'series' => 'Yole OK', 'rank' => '5B', 'registration_price' => '15€'],
                ['type' => 'series_with_rank', 'series' => 'OSIRIS', 'rank' => '5A', 'registration_price' => '20€'],
                ['type' => 'series_with_rank', 'series' => 'Laser', 'rank' => '4', 'registration_price' => '12€'],
                ['type' => 'series_with_rank', 'series' => 'Optimist', 'rank' => '5C', 'registration_price' => '8€'],
                ['type' => 'series_with_rank', 'series' => 'Habitable', 'rank' => '3', 'registration_price' => '25€'],
            ];
            $data['series_button_title'] = 'Classement';
            $data['series_button_text'] = 'Résultats';
            $data['series_button_url'] = 'https://cvvfcm.fr';
            $data['series_links'] = [
                ['type' => 'link', 'text' => 'Tableau officiel', 'url' => 'https://cvvfcm.fr'],
                ['type' => 'link', 'text' => 'Interclassement', 'url' => 'https://cvvfcm.fr'],
                ['type' => 'link', 'text' => 'Photos de l\'édition précédente', 'url' => 'https://cvvfcm.fr'],
            ];
            $data['regatta_informations'] = '<p>La régate rassemble chaque année les meilleurs équipages de la région autour d\'un parcours technique tracé sur le lac, avec un briefing sécurité obligatoire avant chaque départ.</p>';
            $data['services'] = [
                ['type' => 'service', 'name' => 'Buvette', 'availability' => true],
                ['type' => 'service', 'name' => 'Petite restauration', 'availability' => true],
                ['type' => 'service', 'name' => 'Toilettes', 'availability' => true],
                ['type' => 'service', 'name' => 'Douches', 'availability' => true],
                ['type' => 'service', 'name' => 'Possibilité de camper', 'availability' => false],
                ['type' => 'service', 'name' => 'Parking gratuit', 'availability' => true],
            ];
        }

        if ('sea_trip' === $eventType) {
            $data['boats'] = [
                ['type' => 'boat', 'boat_type' => 'Habitable', 'captain' => [$contacts[array_rand($contacts)]->getId()], 'available_seats' => '6', 'approximative_price' => '25€'],
                ['type' => 'boat', 'boat_type' => 'Dériveur', 'captain' => [$contacts[array_rand($contacts)]->getId()], 'available_seats' => '2', 'approximative_price' => '10€'],
                ['type' => 'boat', 'boat_type' => 'Catamaran', 'captain' => [$contacts[array_rand($contacts)]->getId()], 'available_seats' => '4', 'approximative_price' => '18€'],
                ['type' => 'boat', 'boat_type' => 'Optimist', 'captain' => [$contacts[array_rand($contacts)]->getId()], 'available_seats' => '1', 'approximative_price' => '5€'],
            ];
        }

        foreach ($page->getDimensionContents() as $dimensionContent) {
            $dimensionContent->setTemplateData($data);
        }

        $manager->flush();
        $this->contentWorkflow->apply($page, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();
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
