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

final class DefaultPagesFixtures extends Fixture implements DependentFixtureInterface
{
    use HandleTrait;

    private MessageBusInterface $messageBus;

    /** @var list<\Sulu\Bundle\MediaBundle\Entity\Media> */
    private array $medias = [];

    /** @var list<Contact> */
    private array $contacts = [];

    // ── Contenu épique ───────────────────────────────────────────────────────

    private const DESCRIPTIONS = [
        '<p>Sur ce lac que les Ardennes ont fait grand, chaque brise est une invitation à l\'aventure. Les voiles se gonflent et l\'âme du marin s\'éveille dans la lumière rasante du matin.</p>',
        '<p>Il n\'y a pas de mauvais vent pour celui qui sait naviguer. Ici, nous apprenons l\'art de transformer l\'obstacle en allié, la résistance en élan.</p>',
        '<p>La voile est une école de vie. Elle enseigne l\'humilité face aux éléments et l\'audace face aux défis que le lac impose sans compromis.</p>',
        '<p>Kersauson disait que la mer est la seule chose qui ne ment pas. Sur notre lac, cette vérité s\'impose avec la même force implacable.</p>',
        '<p>Le vent des Ardennes a un caractère bien trempé. Né dans les forêts profondes du massif, il dévale les pentes avec une fougue nordique qui surprend les marins habitués aux alizés.</p>',
        '<p>Naviguer, c\'est entrer dans un dialogue millénaire avec les éléments. Chaque bord est une conversation, chaque virement une réponse à l\'invitation du vent.</p>',
        '<p>Sur ce plan d\'eau que nos aïeux ont choisi pour y faire flotter leurs rêves, chaque génération ajoute sa pierre à l\'édifice patient de notre tradition.</p>',
        '<p>La voile enseigne que l\'on ne peut aller droit contre le vent. Il faut ruser, louvoyer, accepter de prendre des bords contraires pour mieux atteindre sa destination.</p>',
    ];

    private const BLOCK_TITLES = [
        "L'appel du large",
        'Quand le lac parle',
        'La leçon du vent',
        'Le courage des eaux calmes',
        'Sur la crête des vagues',
        "L'heure du bord",
        'Mémoire de navigation',
        "L'âme du régatier",
        'Ce que le vent nous apprend',
        'La mer comme maître',
    ];

    private const BLOCK_TEXTS = [
        '<p>Il est des matins où le lac se réveille dans une sérénité trompeuse. Le vent dort encore sous les arbres de la rive et les voiles attendent, patientes, comme des âmes en suspens. C\'est à ces heures que le vrai marin apprend la patience — cette vertu que la mer exige avant toute autre.</p><p>Puis vient la brise, timide d\'abord, assurée ensuite. Et le bateau s\'éveille, vibrant sous l\'impulsion de l\'élément retrouvé.</p>',
        '<p>Kersauson disait que la mer est la seule chose qui ne ment pas. Sur notre lac, cette vérité s\'impose avec la même force. L\'eau révèle l\'homme dans sa nudité essentielle — sans les artifices du quotidien, face à lui-même et aux éléments dans toute leur souveraineté.</p><p>C\'est cette honnêteté brutale que nous cherchons sur l\'eau, et que nous trouvons à chaque navigation, à chaque empannage, à chaque virement réussi ou raté.</p>',
        '<p>Le vent des Ardennes a un caractère bien trempé. Né dans les forêts profondes du massif, il dévale les pentes avec une fougue nordique qui surprend les marins habitués aux alizés doux des mers tropicales. Mais il est généreux et loyal — il ne trompe jamais celui qui a pris la peine de l\'écouter.</p>',
        '<p>La voile enseigne que l\'on ne peut pas aller droit contre le vent. Il faut ruser, louvoyer, accepter de prendre des bords opposés à sa destination pour mieux y parvenir. Cette sagesse dépasse largement le cadre du lac et s\'applique à chaque tempête de l\'existence humaine.</p>',
        '<p>Sur ce plan d\'eau que nos aïeux ont choisi pour y faire flotter leurs rêves, chaque génération a ajouté sa pierre à l\'édifice. Les Optimist d\'hier sont les habitables d\'aujourd\'hui. Les champions de demain s\'y forment dans la tradition rigoureuse et l\'exigence que nous nous imposons.</p>',
        '<p>Il n\'est pas de liberté plus absolue que celle du navigateur quand la voile porte et que la barre répond. Le bruit du monde s\'efface, les préoccupations terrestres coulent avec l\'écume de l\'étrave, et l\'homme retrouve enfin cette plénitude que seul l\'élément peut offrir.</p>',
    ];

    private const LIST_TITLES = [
        'Sur le même chemin de navigation',
        'À explorer également',
        'Pour aller plus loin',
        'Dans la même veine',
    ];

    private const LIST_DESCRIPTIONS = [
        '<p>Découvrez les autres ressources liées à cette thématique.</p>',
        '<p>Pour approfondir votre connaissance des arts de la voile.</p>',
        '<p>Ces pages vous accompagneront dans votre progression sur l\'eau.</p>',
    ];

    private const CTAS = [
        [
            'title' => 'Prenez le large avec nous',
            'description' => '<p>Le vent vous appelle. Rejoignez le CVVFCM et découvrez les joies de la navigation sur le lac des Vieilles Forges. Nos moniteurs diplômés vous accompagnent à chaque bord, dans chaque empannage, vers chaque horizon.</p>',
            'cta_label' => 'Nous rejoindre',
            'cta_url' => 'https://www.cvvfcm.fr',
        ],
        [
            'title' => 'Inscrivez-vous à nos stages',
            'description' => '<p>Du premier bord en Optimist au perfectionnement sur habitable, nos stages s\'adressent à tous les niveaux et à tous les âges. La voile n\'a pas d\'âge — elle a seulement des marins de la première heure et ceux qui nous rejoignent.</p>',
            'cta_label' => 'Voir les stages',
            'cta_url' => 'https://www.cvvfcm.fr',
        ],
        [
            'title' => 'La voile, une vocation',
            'description' => '<p>Rejoindre un club de voile, c\'est bien plus qu\'un loisir. C\'est entrer dans une communauté de passionnés, partager des valeurs, apprendre la mer et l\'humilité magnifique qu\'elle impose à ceux qui s\'y aventurent.</p>',
            'cta_label' => 'Adhérer au club',
            'cta_url' => 'https://www.cvvfcm.fr',
        ],
        [
            'title' => 'Formations et certifications FFVoile',
            'description' => '<p>Le CVVFCM propose des formations officielles de la Fédération Française de Voile. Obtenez vos certifications dans un cadre idyllique sur le lac des Vieilles Forges, encadré par des moniteurs d\'État.</p>',
            'cta_label' => 'Voir les formations',
            'cta_url' => 'https://www.cvvfcm.fr',
        ],
        [
            'title' => 'En compétition pour notre lac',
            'description' => '<p>Chaque régate est une fête du vent. Inscrivez-vous à nos prochaines compétitions et défendez les couleurs du CVVFCM sur le circuit régional et national. L\'honneur du club vous attend au portant.</p>',
            'cta_label' => "S'inscrire",
            'cta_url' => 'https://www.cvvfcm.fr',
        ],
        [
            'title' => 'Votre premier bord vous attend',
            'description' => '<p>Vous n\'avez jamais navigué ? C\'est la plus belle raison de commencer. La première fois que le vent gonfle une voile et que le bateau prend vie sous vos mains, quelque chose change définitivement en vous.</p>',
            'cta_label' => 'Essai gratuit',
            'cta_url' => 'https://www.cvvfcm.fr',
        ],
    ];

    // ── Structure du contenu ─────────────────────────────────────────────────

    /**
     * 4 catégories → sous-catégories → pages default.
     * Chaque entrée :  ['title', 'slug', 'description', 'subcategories']
     * Sous-catégorie : ['title', 'slug', 'description', 'pages']
     * Page :           ['title', 'slug'].
     *
     * @var list<array{
     *   title: string,
     *   slug: string,
     *   description: string,
     *   subcategories: list<array{
     *     title: string,
     *     slug: string,
     *     description: string,
     *     pages: list<array{title: string, slug: string}>
     *   }>
     * }>
     */
    private const STRUCTURE = [
        [
            'title' => "L'École de Voile",
            'slug' => 'ecole-de-voile',
            'description' => '<p>Au CVVFCM, la mer commence sur le lac. Depuis plus de cinquante ans, nous formons les navigateurs de demain avec rigueur, passion et profond respect de l\'élément.</p>',
            'subcategories' => [
                [
                    'title' => 'La Flotte Optimist',
                    'slug' => 'optimist',
                    'description' => '<p>L\'Optimist, ce petit esquif de bois et de toile, est la première école du vent. C\'est là que naissent les champions, dans le silence du lac et le souffle des Ardennes.</p>',
                    'pages' => [
                        ['title' => "Premiers bords sur l'eau", 'slug' => 'premiers-bords'],
                        ['title' => "L'art du virement de bord", 'slug' => 'virement-de-bord'],
                        ['title' => 'Empanner sans craindre le vent', 'slug' => 'empannage'],
                        ['title' => 'Réglages pour les jeunes champions', 'slug' => 'reglages-voiles'],
                        ['title' => 'Régates Optimist des Ardennes', 'slug' => 'regates-ardennes'],
                    ],
                ],
                [
                    'title' => 'Laser et Dériveurs',
                    'slug' => 'laser',
                    'description' => '<p>Le Laser est le verdict du vent rendu sans appel. Seul face aux éléments, le régatier apprend à se surpasser dans une communion brutale avec l\'élément.</p>',
                    'pages' => [
                        ['title' => 'Initiation au Laser', 'slug' => 'initiation'],
                        ['title' => 'Le planing et ses vertiges', 'slug' => 'planing'],
                        ['title' => 'Maîtriser le vent thermique', 'slug' => 'vent-thermique'],
                        ['title' => 'Préparation physique du régatier', 'slug' => 'preparation-physique'],
                        ['title' => 'Tactique de course en dériveur', 'slug' => 'tactique-course'],
                    ],
                ],
                [
                    'title' => 'Habitables et Navigation Côtière',
                    'slug' => 'habitables',
                    'description' => '<p>Sur les habitables, on apprend que la voile est aussi une philosophie de vie, où l\'équipage forme une âme collective face à l\'imprévisible.</p>',
                    'pages' => [
                        ['title' => 'Naviguer en équipage, une philosophie', 'slug' => 'philosophie-equipage'],
                        ['title' => 'La manœuvre du spi', 'slug' => 'manoeuvre-spi'],
                        ['title' => 'Navigation nocturne sur le lac', 'slug' => 'navigation-nocturne'],
                        ['title' => 'Météorologie pour navigateurs avisés', 'slug' => 'meteorologie'],
                        ['title' => 'Le règlement des courses en équipage', 'slug' => 'reglement-courses'],
                        ['title' => 'Entretien du gréement et des voiles', 'slug' => 'entretien-greement'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Compétitions & Régates',
            'slug' => 'competitions-regates',
            'description' => '<p>La régate est la mesure de l\'homme par le vent. Chaque marque doublée est une victoire sur soi-même avant d\'être une victoire sur les autres.</p>',
            'subcategories' => [
                [
                    'title' => 'Trophée des Ardennes',
                    'slug' => 'trophee-ardennes',
                    'description' => '<p>Le Trophée des Ardennes est notre grand rendez-vous annuel avec la gloire. Sur ces eaux familières, les champions se révèlent à eux-mêmes.</p>',
                    'pages' => [
                        ['title' => 'Le grand rendez-vous annuel', 'slug' => 'presentation'],
                        ['title' => 'Palmarès et légendes du lac', 'slug' => 'palmares'],
                        ['title' => 'Règlement sportif', 'slug' => 'reglement'],
                        ['title' => 'Inscriptions et jauge', 'slug' => 'inscriptions'],
                        ['title' => 'Les parcours homologués du lac', 'slug' => 'parcours'],
                    ],
                ],
                [
                    'title' => 'Circuit Régional Grand Est',
                    'slug' => 'circuit-regional',
                    'description' => '<p>Le circuit Grand Est forge les navigateurs dans l\'épreuve répétée. Chaque étape est un pas vers l\'excellence, chaque défaite une leçon gravée dans la quille.</p>',
                    'pages' => [
                        ['title' => 'Calendrier du circuit 2025', 'slug' => 'calendrier-2025'],
                        ['title' => 'Classement général provisoire', 'slug' => 'classement'],
                        ['title' => 'Système de points et qualifications', 'slug' => 'points-qualifications'],
                        ['title' => 'Sélection pour les championnats de France', 'slug' => 'selection-france'],
                    ],
                ],
                [
                    'title' => 'Championnats Nationaux',
                    'slug' => 'championnats',
                    'description' => '<p>Quand nos couleurs flottent sur les plans d\'eau nationaux, c\'est tout le club qui navigue avec nos champions. Leur gloire est notre gloire collective.</p>',
                    'pages' => [
                        ['title' => 'Nos champions, notre fierté', 'slug' => 'nos-champions'],
                        ['title' => 'La Route du Rhum — nos représentants', 'slug' => 'route-du-rhum'],
                        ['title' => 'Records sur le lac des Vieilles Forges', 'slug' => 'records'],
                        ['title' => 'Préparation aux grands circuits', 'slug' => 'preparation-circuits'],
                        ['title' => 'Hommage aux anciens champions', 'slug' => 'hommage'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Vie du Club',
            'slug' => 'vie-du-club',
            'description' => '<p>Le club est plus qu\'une association. C\'est une famille rassemblée par la passion de l\'eau, du vent et de la liberté que confère seule la navigation à la voile.</p>',
            'subcategories' => [
                [
                    'title' => 'Histoire & Traditions',
                    'slug' => 'histoire',
                    'description' => '<p>Notre histoire est celle de passionnés qui ont choisi de braver les vents ardennais pour offrir à leur lac la dignité d\'un vrai club de voile.</p>',
                    'pages' => [
                        ['title' => 'Les origines du CVVFCM', 'slug' => 'origines'],
                        ['title' => 'Les fondateurs légendaires', 'slug' => 'fondateurs'],
                        ['title' => 'Cinquante ans sur les eaux ardennaises', 'slug' => 'cinquante-ans'],
                        ['title' => 'Le lac des Vieilles Forges, notre domaine', 'slug' => 'le-lac'],
                        ['title' => 'Les grandes traversées de notre histoire', 'slug' => 'grandes-traversees'],
                        ['title' => 'Hommage aux hommes de barre', 'slug' => 'hommage-hommes-de-barre'],
                    ],
                ],
                [
                    'title' => 'Adhésion & Vie Associative',
                    'slug' => 'adhesion',
                    'description' => '<p>Rejoindre le CVVFCM, c\'est choisir de naviguer avec des hommes et des femmes qui partagent le même amour de l\'élément et de la liberté.</p>',
                    'pages' => [
                        ['title' => 'Rejoindre notre équipage', 'slug' => 'rejoindre'],
                        ['title' => "Tarifs et formules d'adhésion", 'slug' => 'tarifs'],
                        ['title' => 'Le bureau et les élus', 'slug' => 'bureau-elus'],
                        ['title' => 'Assemblée générale annuelle', 'slug' => 'assemblee-generale'],
                        ['title' => 'Nos partenaires et mécènes', 'slug' => 'partenaires'],
                    ],
                ],
                [
                    'title' => 'Flotte & Équipements',
                    'slug' => 'flotte',
                    'description' => '<p>Notre flotte est l\'âme matérielle du club. Chaque bateau est une promesse d\'aventure, soigneusement entretenu par des mains passionnées.</p>',
                    'pages' => [
                        ['title' => 'La flotte du club', 'slug' => 'presentation'],
                        ['title' => 'Le port et les pontons', 'slug' => 'port-pontons'],
                        ['title' => 'Réservation des bateaux', 'slug' => 'reservation'],
                        ['title' => 'Entretien participatif, devoir du marin', 'slug' => 'entretien-participatif'],
                        ['title' => 'Nouveaux équipements 2025', 'slug' => 'equipements-2025'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Technique & Navigation',
            'slug' => 'technique-navigation',
            'description' => '<p>La technique est le langage que nous apprenons pour dialoguer avec le vent. Chaque nœud de vitesse gagné est une conversation plus profonde avec l\'élément.</p>',
            'subcategories' => [
                [
                    'title' => 'Météo & Conditions',
                    'slug' => 'meteo',
                    'description' => '<p>Le vent ne pardonne pas l\'ignorance. Connaître la météo, c\'est respecter l\'élément avant de s\'y abandonner avec toute la confiance du marin averti.</p>',
                    'pages' => [
                        ['title' => 'Lire les vents ardennais', 'slug' => 'vents-ardennais'],
                        ['title' => 'Les effets de site sur notre lac', 'slug' => 'effets-de-site'],
                        ['title' => 'La règle des vingt nœuds', 'slug' => 'regle-vingt-noeuds'],
                        ['title' => 'Outils météo du navigateur moderne', 'slug' => 'outils-meteo'],
                        ['title' => 'Journal de bord climatique du lac', 'slug' => 'journal-climatique'],
                    ],
                ],
                [
                    'title' => 'Sécurité & Sauvetage',
                    'slug' => 'securite',
                    'description' => '<p>La prudence n\'est pas la peur. C\'est la sagesse du marin qui sait que l\'élément ne transige pas avec l\'imprudence ni avec la vanité.</p>',
                    'pages' => [
                        ['title' => "La règle d'or en mer", 'slug' => 'regle-or'],
                        ['title' => 'Procédures de chavirage', 'slug' => 'chavirage'],
                        ['title' => 'Matériel de sécurité obligatoire', 'slug' => 'materiel-obligatoire'],
                        ['title' => 'Formation premiers secours nautiques', 'slug' => 'formation-secours'],
                        ['title' => 'Signaux de détresse sur le lac', 'slug' => 'signaux-detresse'],
                    ],
                ],
                [
                    'title' => 'Réglages & Performances',
                    'slug' => 'reglages',
                    'description' => '<p>Le réglage est l\'art de faire parler le bateau. Quand la voile est parfaitement réglée, le bateau s\'efface et seul le vent demeure.</p>',
                    'pages' => [
                        ['title' => 'Réglage du mât — les fondamentaux', 'slug' => 'reglage-mat'],
                        ['title' => 'La quête du profil de voile parfait', 'slug' => 'profil-voile'],
                        ['title' => "Le vang, l'outil méconnu", 'slug' => 'le-vang'],
                        ['title' => 'Optimisation du centre de gravité', 'slug' => 'centre-gravite'],
                        ['title' => 'Carénage et entretien des carènes', 'slug' => 'carenage'],
                        ['title' => 'Les polaires de vitesse', 'slug' => 'polaires-vitesse'],
                    ],
                ],
            ],
        ],
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
        $root = $this->pageRepository->findOneBy(['parentId' => null, 'webspaceKey' => 'cvvfcm']);
        $this->medias = array_values($this->mediaRepository->findAll());
        $this->contacts = array_values(array_filter(
            $this->contactRepository->findAll(),
            static fn (Contact $contact): bool => (bool) $contact->getMainEmail(),
        ));

        foreach (self::STRUCTURE as $categoryData) {
            $categoryUrl = '/'.$categoryData['slug'];
            $category = $this->createCategoryPage($manager, $root, $categoryData['title'], $categoryUrl, $categoryData['description']);

            foreach ($categoryData['subcategories'] as $subData) {
                $subUrl = $categoryUrl.'/'.$subData['slug'];
                $subcategory = $this->createCategoryPage($manager, $category, $subData['title'], $subUrl, $subData['description']);

                foreach ($subData['pages'] as $pageData) {
                    $this->createDefaultPage($manager, $subcategory, $pageData['title'], $subUrl.'/'.$pageData['slug']);
                }
            }
        }
    }

    private function createCategoryPage(
        ObjectManager $manager,
        Page $parent,
        string $title,
        string $url,
        string $description,
    ): Page {
        /** @var Page $page */
        $page = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $parent->getWebspaceKey(),
                    $parent->getId(),
                    ['title' => $title, 'url' => $url, 'template' => 'category', 'locale' => 'fr'],
                ),
            ),
        );
        $page->setWebspaceKey($parent->getWebspaceKey());
        $page->setParent($parent);

        foreach ($page->getDimensionContents() as $dim) {
            $dim->addNavigationContext('main');
            $dim->setTemplateData([
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'media' => ['id' => $this->randomMediaId()],
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($page, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();

        return $page;
    }

    private function createDefaultPage(ObjectManager $manager, Page $parent, string $title, string $url): void
    {
        /** @var Page $page */
        $page = $this->handle(
            new Envelope(
                new CreatePageMessage(
                    $parent->getWebspaceKey(),
                    $parent->getId(),
                    ['title' => $title, 'url' => $url, 'template' => 'default', 'locale' => 'fr'],
                ),
            ),
        );
        $page->setWebspaceKey($parent->getWebspaceKey());
        $page->setParent($parent);

        foreach ($page->getDimensionContents() as $dim) {
            $dim->addNavigationContext('main');
            $dim->setTemplateData([
                'title' => $title,
                'url' => $url,
                'description' => self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)],
                'media' => ['id' => $this->randomMediaId()],
                'blocks' => $this->randomBlocks($parent),
                // Not every page carries them, so both the empty and the filled rendering
                // of the links-and-contacts row are covered.
                ...$this->randomLinksAndContacts(),
            ]);
        }

        $manager->flush();
        $this->contentWorkflow->apply($page, ['locale' => 'fr'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $manager->flush();
    }

    // ── Génération de blocs aléatoires ──────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function randomBlocks(Page $parent): array
    {
        $types = ['title_image_text', 'gallery', 'list', 'cta'];
        shuffle($types);
        $selected = array_slice($types, 0, random_int(2, 4));

        return array_map(fn (string $type): array => match ($type) {
            'title_image_text' => $this->blockTitleImageText(),
            'gallery' => $this->blockGallery(),
            'list' => $this->blockList($parent),
            'cta' => $this->blockCta(),
        }, $selected);
    }

    /**
     * The block renders three ways — no image, one image, a carousel — so the fixtures
     * cover all three.
     *
     * @return array<string, mixed>
     */
    private function blockTitleImageText(): array
    {
        return [
            'type' => 'title_image_text',
            'title' => self::BLOCK_TITLES[array_rand(self::BLOCK_TITLES)],
            'medias' => $this->randomMediaSelection(0, 3),
            'text' => self::BLOCK_TEXTS[array_rand(self::BLOCK_TEXTS)],
        ];
    }

    /** @return array<string, mixed> */
    private function blockGallery(): array
    {
        return [
            'type' => 'gallery',
            'medias' => $this->randomMediaSelection(3, 8),
        ];
    }

    /**
     * @return array{}|array{links: list<array<string, string>>, contact: list<string>}
     */
    private function randomLinksAndContacts(): array
    {
        if ([] === $this->contacts || 0 === random_int(0, 2)) {
            return [];
        }

        return [
            'links' => [
                ['type' => 'link', 'title' => 'Tableau Officiel', 'url' => 'https://cvvfcm.fr'],
                ['type' => 'link', 'title' => 'CVVFCM', 'url' => 'https://cvvfcm.fr'],
            ],
            'contact' => ['c'.$this->contacts[array_rand($this->contacts)]->getId()],
        ];
    }

    /** @return array{displayOption: null, ids: list<int>} */
    private function randomMediaSelection(int $min, int $max): array
    {
        $count = min(random_int($min, $max), \count($this->medias));
        $keys = 0 === $count ? [] : (array) array_rand($this->medias, $count);

        return [
            'displayOption' => null,
            'ids' => array_map(fn (int|string $k): int => $this->medias[(int) $k]->getId(), $keys),
        ];
    }

    /** @return array<string, mixed> */
    private function blockList(Page $parent): array
    {
        return [
            'type' => 'list',
            'title' => self::LIST_TITLES[array_rand(self::LIST_TITLES)],
            'pages' => [
                'dataSource' => $parent->getId(),
                'includeSubFolders' => true,
                'limitResult' => 6,
                'tags' => [],
                'tagOperator' => 'or',
                'categories' => [],
                'categoryOperator' => 'and',
            ],
            'description' => self::LIST_DESCRIPTIONS[array_rand(self::LIST_DESCRIPTIONS)],
        ];
    }

    /** @return array<string, mixed> */
    private function blockCta(): array
    {
        return array_merge(['type' => 'cta'], self::CTAS[array_rand(self::CTAS)]);
    }

    private function randomMediaId(): int
    {
        return $this->medias[array_rand($this->medias)]->getId();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [MediaFixtures::class, ContactsFixtures::class];
    }
}
