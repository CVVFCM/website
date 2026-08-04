<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Application\Message\CreateSnippetMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The club's official documents, readable by Forgie through the club_rules tool.
 * Snippets on purpose: no URL, absent from the public search index, the sitemap
 * and the site_pages tool. Content transcribed from the signed PDFs.
 */
final class LegalDocumentSnippetFixtures extends Fixture
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
        $documents = [
            [
                'title' => 'Statuts du CVVFCM',
                'document_key' => 'statuts',
                'reference' => 'Adoptés lors de l\'Assemblée Générale Extraordinaire du 24/01/2026',
                'content' => self::STATUTS,
            ],
            [
                'title' => 'Règlement intérieur du CVVFCM',
                'document_key' => 'reglement_interieur',
                'reference' => 'Adopté lors de l\'Assemblée Générale Extraordinaire du 24/01/2026',
                'content' => self::REGLEMENT_INTERIEUR,
            ],
            [
                'title' => 'Règlement de la police de la navigation du lac des Vieilles Forges',
                'document_key' => 'reglement_lac',
                'reference' => 'Arrêté préfectoral du 08/04/1976 (Préfet des Ardennes)',
                'content' => self::REGLEMENT_LAC,
            ],
        ];

        foreach ($documents as $document) {
            /** @var SnippetInterface $snippet */
            $snippet = $this->handle(
                new Envelope(
                    new CreateSnippetMessage([
                        'locale' => 'fr',
                        'template' => 'legal_document',
                        ...$document,
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
        }
    }

    private const string STATUTS = <<<'HTML'
        <h2>Titre I — But et composition</h2>
        <h3>Article 1 — Dénomination</h3>
        <p>Le Club de Voile des Vieilles-Forges de Charleville-Mézières (CVVFCM) est une association déclarée, régie par la loi du 1er juillet 1901, les lois et règlements en vigueur ainsi que par les présents statuts.</p>
        <p>Le CVVFCM est membre du Comité Départemental de Voile des Ardennes.</p>
        <h3>Article 2 — Objet</h3>
        <p>2.1. Le CVVFCM a pour but de développer le goût et la pratique de la voile sous toutes ses formes : plaisance, compétition sportive et loisir.</p>
        <p>2.2. Le CVVFCM est constitué dans les conditions prévues par le chapitre II du titre I de la loi n° 84-610 du 16 juillet 1984 modifiée relative à l'organisation et à la promotion des activités physiques et sportives, et par le décret n° 85-236 du 13 février 1985 modifié, relatif aux statuts types des Fédérations Sportives.</p>
        <p>2.3. Les moyens d'action du CVVFCM sont les cours, conférences, séances d'entraînement, compétitions sportives et, en général, tout exercice et toute initiative propre à la formation physique et morale de la jeunesse.</p>
        <p>2.4. Le CVVFCM s'interdit toute discussion ou manifestation contraire à son objet.</p>
        <h3>Article 3 — Siège social</h3>
        <p>Le siège social est fixé à : Maison des Associations, 5 rue Jean Moulin, 08000 Charleville-Mézières. Il pourra être transféré par simple décision du Comité de Direction soumise à ratification de l'Assemblée Générale la plus proche.</p>
        <h3>Article 4 — Durée</h3>
        <p>La durée du CVVFCM est illimitée.</p>
        <h3>Article 5 — Affiliation</h3>
        <p>5.1. Le CVVFCM est affilié à la Fédération Française de Voile. Son organisation est compatible avec les statuts du Comité Départemental de Voile des Ardennes et ceux de la Fédération Française de Voile.</p>
        <p>5.2. Tous les membres actifs du CVVFCM sont licenciés à la Fédération Française de Voile.</p>
        <h3>Article 6 — Membre</h3>
        <p>Le CVVFCM se compose de membres d'honneur, de membres actifs et de membres donateurs et bienfaiteurs.</p>
        <p>6.1. Les membres d'honneur sont des personnes physiques qui rendent ou ont rendu des services importants au CVVFCM. Ils sont dispensés de cotisation annuelle mais conservent le droit de participer, avec voix délibérative, aux Assemblées Générales.</p>
        <p>6.2. Les membres actifs sont des personnes physiques qui participent régulièrement aux activités du CVVFCM. Ils ont seuls le droit de vote à l'Assemblée Générale.</p>
        <p>6.3. Les membres donateurs et bienfaiteurs sont des personnes morales ou physiques qui contribuent à aider le CVVFCM par des dons manuels dont le montant minimum est fixé chaque année par le Comité de Direction. Ils n'ont pas le droit de vote à l'Assemblée Générale mais peuvent y être invités par le Comité de Direction.</p>
        <p>6.4. Les titres de membre d'honneur, donateur et bienfaiteur sont décernés par le Comité de Direction.</p>
        <h3>Article 7 — Conditions d'adhésion</h3>
        <p>Pour être membre actif du CVVFCM, il faut réunir trois conditions : être agréé par le Comité de Direction et respecter les présents statuts (7.1) ; être licencié à la Fédération Française de Voile (7.2) ; avoir réglé le montant de la cotisation annuelle (7.3).</p>
        <h3>Article 8 — Cotisation</h3>
        <p>8.1. La cotisation due par chaque membre, sauf pour les membres d'honneur, donateurs ou bienfaiteurs, est fixée annuellement par l'Assemblée Générale sur proposition du Comité de Direction.</p>
        <p>8.2. Toute cotisation payée est définitivement acquise par le CVVFCM.</p>
        <h3>Article 9 — Perte de la qualité de membre</h3>
        <p>La qualité de membre se perd par : la démission adressée par écrit au Président (9.1) ; le décès (9.2) ; la radiation prononcée par le Comité de Direction pour non-paiement de la cotisation annuelle, infraction aux statuts ou motif grave portant préjudice moral ou matériel au CVVFCM ou à ses membres, en particulier l'utilisation ou l'incitation à l'utilisation de produits dopants (9.3) ; la radiation prononcée par la Fédération Française de Voile (9.4). Avant toute décision d'exclusion ou de radiation, le membre concerné est invité à fournir des explications écrites au Comité de Direction.</p>
        <h3>Article 10 — Responsabilité des membres</h3>
        <p>Aucun membre du CVVFCM n'est personnellement responsable des engagements contractés par le CVVFCM. Seul le patrimoine du CVVFCM répond de ses engagements.</p>
        <h2>Titre II — L'Assemblée Générale</h2>
        <h3>Article 11 — Convocation et compétence</h3>
        <p>11.1. L'Assemblée Générale se compose de tous les membres actifs âgés de seize ans au moins au jour de l'Assemblée, ou de leur représentant légal si le membre a moins de 16 ans.</p>
        <p>11.4. Elle est convoquée par le Président et se réunit au moins une fois par an, à la date fixée par le Comité de Direction ; elle se réunit en outre chaque fois que sa convocation est demandée par le Comité de Direction ou par le quart au moins de ses membres.</p>
        <p>11.5. La convocation, accompagnée de l'ordre du jour, est adressée par lettre ordinaire ou par voie électronique, 15 jours au moins à l'avance.</p>
        <p>11.6. La présidence de l'Assemblée appartient au Président ou, en son absence, au Vice-Président.</p>
        <p>11.7. Chaque membre peut donner pouvoir à un autre membre ; nul ne peut disposer de plus de deux pouvoirs en sus de sa voix. Les votes par correspondance sont interdits.</p>
        <p>11.9 à 11.12. L'Assemblée Générale entend les rapports sur la gestion du Comité de Direction et sur la situation morale et financière ; elle approuve les comptes, vote le budget prévisionnel, fixe la cotisation annuelle, pourvoit à l'élection des membres du Comité de Direction et délibère sur les questions inscrites à l'ordre du jour.</p>
        <p>11.13. Les décisions, en dehors de l'élection du Président, de la révocation du Comité de Direction, de la modification des statuts et de la dissolution, sont prises à la majorité simple des membres présents ou représentés.</p>
        <p>11.14. Les votes ont lieu à main levée ou à bulletin secret ; les élections des membres du Comité de Direction ont obligatoirement lieu à bulletin secret.</p>
        <p>11.15. Il est tenu procès-verbal de l'Assemblée Générale, signé par le Président et le Secrétaire Général.</p>
        <h2>Titre III — Administration</h2>
        <h3>Article 12 — Composition et compétence</h3>
        <p>12.1. Le CVVFCM est administré par un Comité de Direction de 6 à 15 membres.</p>
        <p>12.2. Les membres du Comité de Direction sont élus au scrutin secret, à la majorité simple, par l'Assemblée Générale et en son sein pour une durée de quatre années. Ils sont rééligibles.</p>
        <p>12.3. En cas de vacance, le Comité de Direction pourvoit provisoirement au remplacement de ses membres ; le remplacement définitif intervient à la plus prochaine Assemblée Générale.</p>
        <p>12.4. Sont éligibles les personnes âgées de seize ans au moins au jour de l'élection, jouissant de leurs droits civils et politiques, titulaires d'une licence annuelle FFV de l'année en cours, membres actifs depuis plus de six mois et à jour de leurs cotisations. La moitié au moins des sièges doivent être occupés par des membres ayant la majorité légale.</p>
        <p>12.5 et 12.6. L'appel à candidatures précise le nombre de sièges à pourvoir ; les candidatures sont adressées au club 20 jours au moins avant l'élection, avec nom, prénom, adresse, nationalité, profession, numéro de licence, motivations et signature.</p>
        <h3>Article 13 — Fonctionnement</h3>
        <p>Le Comité de Direction se réunit chaque fois qu'il est convoqué par le Président ou à la demande d'au moins la moitié de ses membres, si possible au moins quatre fois par an (13.1). La présence du tiers au moins de ses membres est nécessaire pour délibérer valablement (13.2). Les délibérations sont prises à la majorité simple des présents ; en cas d'égalité, la voix du Président est prépondérante (13.3). Il est tenu procès-verbal des séances, signé par le Président et le Secrétaire Général (13.4).</p>
        <h3>Article 14 — Révocation</h3>
        <p>L'Assemblée Générale peut mettre fin au mandat du Comité de Direction avant son terme par un vote intervenant dans des conditions cumulatives : convocation à la demande du tiers de ses membres, présence des deux tiers des membres, révocation votée à la majorité absolue des suffrages exprimés et des bulletins blancs.</p>
        <h3>Article 15 — Indemnisation</h3>
        <p>Les membres du Comité de Direction ne peuvent recevoir aucune rétribution pour les fonctions qui leur sont confiées. Les frais et débours occasionnés par leur mandat leur sont remboursés au vu des pièces justificatives ; le rapport financier en fait mention.</p>
        <h3>Article 16 — Pouvoirs</h3>
        <p>Le Comité de Direction est investi des pouvoirs les plus étendus dans la limite des buts du CVVFCM et des résolutions de l'Assemblée Générale : admissions et exclusions des membres, titres honorifiques, ouverture de comptes, emprunts, subventions, autorisation donnée au Président, au Trésorier et au Secrétaire Général pour les actes et contrats nécessaires, signature des contrats de travail. Il peut déléguer tout ou partie de ses attributions au Bureau ou à certains de ses membres.</p>
        <h3>Article 17 — Élection du Président</h3>
        <p>Le Président est choisi parmi les membres du Comité de Direction, sur proposition de celui-ci, et élu au scrutin secret à la majorité absolue des suffrages valablement exprimés et des bulletins blancs. Son mandat prend fin avec celui du Comité de Direction.</p>
        <h3>Article 18 — Nomination et fonctionnement du Bureau</h3>
        <p>18.1. Le Président propose au Comité de Direction un Bureau composé du Président, d'un Secrétaire Général, d'un Trésorier et d'un Vice-Président, élu en son sein au scrutin secret pour quatre ans.</p>
        <p>18.3. Les fonctions de Président, Secrétaire Général et Trésorier ne sont pas cumulables.</p>
        <p>18.5 à 18.7. Le Bureau se réunit sur convocation du Président ; les décisions sont prises à la majorité des présents, la voix du Président étant prépondérante en cas de partage. La présence de la moitié au moins de ses membres est nécessaire. Tout membre du Bureau ayant manqué, sans excuse, trois séances consécutives pourra être considéré comme démissionnaire.</p>
        <h3>Article 19 — Rôle des membres du Bureau</h3>
        <p>19.1. Le Président dirige les travaux du Comité de Direction et assure le fonctionnement du CVVFCM qu'il représente en justice et dans tous les actes de la vie civile.</p>
        <p>19.2. Le Secrétaire Général est chargé de la correspondance, des convocations, des procès-verbaux et de leur transcription sur les registres légaux.</p>
        <p>19.3. Le Trésorier tient les comptes du CVVFCM, effectue les paiements, perçoit les recettes sous la surveillance du Président, dresse le bilan annuel et le budget prévisionnel.</p>
        <h2>Titre IV — Dotations et ressources annuelles</h2>
        <h3>Article 20 — Dotations</h3>
        <p>La dotation comprend les capitaux provenant des libéralités et la partie des excédents de ressources non nécessaire au fonctionnement de l'exercice suivant.</p>
        <h3>Article 21 — Ressources</h3>
        <p>Les ressources annuelles comprennent : le revenu des biens, les cotisations des membres, les produits des manifestations et du partenariat, les subventions (État, collectivités, établissements publics, FFVoile), les libéralités, les ressources exceptionnelles (spectacles, tombolas…), les rétributions pour services rendus et les produits financiers.</p>
        <h3>Article 22 — Comptabilité</h3>
        <p>La comptabilité est tenue conformément aux lois et règlements en vigueur et fait apparaître annuellement un compte d'exploitation, le résultat de l'exercice et un bilan agréé.</p>
        <h2>Titre V — Modification des statuts et dissolution</h2>
        <h3>Article 23 — Modification des statuts</h3>
        <p>Les statuts peuvent être modifiés par l'Assemblée Générale sur proposition du Comité de Direction ou du dixième des membres. La convocation est adressée 15 jours au moins à l'avance. Les statuts ne peuvent être modifiés qu'à la majorité des deux tiers des membres présents ou représentés.</p>
        <h3>Article 24 — Dissolution</h3>
        <p>L'Assemblée Générale ne peut prononcer la dissolution du CVVFCM que si elle est convoquée spécialement à cet effet, dans les conditions de l'article 23.</p>
        <h3>Article 25 — Liquidation des biens</h3>
        <p>En cas de dissolution, l'Assemblée Générale désigne un ou plusieurs commissaires chargés de la liquidation des biens et attribue l'actif net au Comité Départemental de Voile des Ardennes.</p>
        <h2>Titre VI — Surveillance et règlement intérieur</h2>
        <h3>Article 26 — Compte-rendu aux autorités administratives</h3>
        <p>Le Président fait connaître à la Ligue Régionale, au Comité Départemental de Voile et à la Préfecture les changements intervenus dans la direction, la modification des statuts, la dissolution et la liquidation des biens. Le rapport annuel d'activité, le rapport moral et le rapport financier sont adressés chaque année à la Ligue Régionale et au Comité Départemental de Voile.</p>
        <h3>Article 27 — Règlement intérieur</h3>
        <p>Un règlement intérieur pourra être établi et librement modifié par le Comité de Direction sans avoir à être approuvé par l'Assemblée Générale. Il fixe les points non prévus par les statuts, notamment le fonctionnement pratique des activités du CVVFCM.</p>
        <p>Les présents statuts ont été adoptés par l'Assemblée Générale Extraordinaire du 24/01/2026 à Charleville-Mézières.</p>
        HTML;

    private const string REGLEMENT_INTERIEUR = <<<'HTML'
        <h2>1. Accès au club</h2>
        <p>L'accès au club (locaux, terrain, rampe de mise à l'eau) est exclusivement réservé aux membres à jour de leur cotisation annuelle incluant la licence FFVoile. Le renouvellement des cotisations a lieu au cours du premier trimestre de chaque année. Le prêt des clés d'accès est nominatif : il est interdit de les dupliquer ou de les transmettre sans passer par le bureau du club. Le non-renouvellement ou la radiation implique la restitution des clés.</p>
        <h2>2. Navigation et sécurité</h2>
        <p>La navigation et la baignade sont formellement interdites en dehors des zones délimitées par le gestionnaire et/ou le propriétaire du plan d'eau (Conseil Départemental des Ardennes et EDF).</p>
        <p>Tout membre utilisant les matériels nautiques du club, ou participant avec son matériel personnel, doit porter une brassière de sécurité aux normes agréées : port du gilet obligatoire pour la voile légère et pour la voile habitable en solitaire ; pour la voile habitable à plusieurs, le port du gilet est facultatif, à la discrétion et sous la responsabilité du chef de bord, avec à bord un nombre de gilets équivalent au nombre de personnes embarquées.</p>
        <p>Tout membre doit s'assurer, avant d'aller naviguer, qu'il est apte à le faire en fonction de ses capacités et des conditions météorologiques, et qu'un moyen d'intervention peut être mis à sa disposition si besoin. Seules les activités organisées et encadrées par le CVVFCM et ses moniteurs sont surveillées (stages, école de voile, accueil de groupes, régates).</p>
        <p>L'utilisation des bateaux moteurs de plus de 4,5 kW est soumise à la possession du permis bateau et au respect de la division 240. Le port du coupe-circuit est obligatoire. Il est formellement interdit d'utiliser les bateaux à moteurs thermiques du club sans autorisation du comité ; leur usage est réservé à la sécurisation et à l'encadrement des activités.</p>
        <p>La navigation sur le lac doit se faire dans le respect des autres utilisateurs et acteurs présents sur et au bord du lac (pêche, canoë-kayak, etc.).</p>
        <h2>3. Matériels</h2>
        <p>Le rangement des bateaux, planches à voile et remorques doit se faire, après accord du CVVFCM, dans les locaux ou sur les parties extérieures prévues à cet effet. Le stockage de matériels personnels ne peut se faire qu'avec accord du comité.</p>
        <p>Les bateaux de propriétaires, membres du club, ne peuvent être utilisés en l'absence de leurs propriétaires. Tout matériel doit comporter une identification permettant d'en connaître le propriétaire. Les propriétaires ont l'obligation de maintenir leur matériel en parfait état de propreté, et leur matériel roulant en état de rouler (déplacement au moins une fois par an).</p>
        <p>La mise au mouillage d'un bateau de propriétaire, sur corps mort saisonnier du club, est subordonnée aux règles d'attribution en vigueur et à l'accord du Bureau.</p>
        <h2>4. Responsabilité</h2>
        <p>Le CVVFCM dégage toute responsabilité concernant le vol, l'incendie ou les détériorations pouvant survenir aux bateaux et matériels stationnés ou laissés dans l'enceinte du club. Les propriétaires sont responsables de leurs bateaux qu'ils doivent assurer individuellement.</p>
        <p>Chaque membre engage sa responsabilité propre dans la pratique de son sport. L'utilisation du matériel et des infrastructures du club est considérée comme une activité club : toute personne doit être licenciée. À caractère exceptionnel, des écarts pourront être tolérés, sur accord préalable du comité.</p>
        <h2>5. Assurances</h2>
        <p>Les membres sont personnellement responsables des accidents matériels ou corporels qu'ils pourraient provoquer à terre ou sur le plan d'eau. Le club propose à tous ses membres l'assurance complémentaire dommages corporels prévue en complément de la licence FFVoile. Le club n'est pas responsable des vols d'effets personnels ni des embarcations et matériels de propriétaires utilisés ou stockés au club.</p>
        <h2>6. Club house</h2>
        <p>L'accès au club house n'est possible qu'en présence d'un membre du Comité Directeur (délégation possible pour d'anciens membres du comité, agréés par le Comité Directeur en exercice).</p>
        <p>Il est formellement interdit : de coucher dans le club house, les vestiaires et dépendances ; de diffuser de la musique sans accord préalable de la SACEM ; d'organiser des réunions privées sans accord et signature préalable de la convention prévue ; de conserver des denrées alimentaires plus de 48 heures dans les frigos.</p>
        <p>Le club house peut être mis ponctuellement à disposition des membres ou d'associations sportives extérieures (convention type et participation aux frais fixée par le Comité Directeur), sous réserve de disponibilité. L'utilisateur s'assure de l'extinction des lumières et radiateurs, de la fermeture des robinets d'eau, des portes, impostes et portails.</p>
        <h2>7. Parking</h2>
        <p>Le stationnement des voitures et remorques des membres se fait exclusivement sur la partie du terrain prévue à cet effet et sous leur seule responsabilité. La circulation des véhicules motorisés dans l'enceinte du club et sur les berges doit se faire au pas.</p>
        <h2>8. Les chiens et autres animaux domestiques</h2>
        <p>Les animaux domestiques des membres sont admis dans l'enceinte du club sous leur seule responsabilité, dans le respect de la sécurité et de la tranquillité d'autrui. Ils doivent être tenus en laisse (courte afin d'éviter aux personnes présentes de trébucher) dans l'enceinte du club et sur le site des Vieilles Forges. Les propriétaires doivent assurer l'hygiène de leur animal et ramasser leurs déjections.</p>
        <h2>9. Règle générale</h2>
        <p>Tout membre du CVVFCM doit s'obliger à : conserver un bon état de propreté aux locaux, terrain et abords du club ; respecter la tranquillité et les biens d'autrui ; nettoyer puis ranger à son départ les matériels du club qu'il a utilisés.</p>
        <h2>10. Non-renouvellement des cotisations</h2>
        <p>Tout matériel de propriétaire abandonné pendant deux saisons sera pris en compte par le bureau et mis à disposition du club afin d'éviter les épaves. Si le propriétaire se fait connaître dans un délai de deux ans, le matériel lui sera restitué en l'état après règlement des arriérés. Au bout de trois ans sans manifestation, le matériel pourra être vendu ou détruit s'il est inutilisable. En cas d'arriérés de cotisation, un propriétaire ne pourra redevenir membre qu'après paiement des cotisations antérieures dues.</p>
        <h2>11. Exclusion d'un membre</h2>
        <p>Le Comité pourra décider l'exclusion de tout membre, sans compensation, si celui-ci ne respecte pas les règles de savoir-vivre qui régissent la communauté du club (relations humaines, ordre, propreté du plan d'eau, des abords, des équipements, des voies d'accès, etc.) ainsi que le présent règlement intérieur.</p>
        <h2>12. Modification du règlement intérieur</h2>
        <p>Le présent règlement intérieur peut être modifié ou complété au cours d'une assemblée générale ou d'une réunion de bureau ; dans ce dernier cas, il sera présenté à la prochaine assemblée générale.</p>
        <p>Le présent règlement intérieur, qui annule et remplace celui du 27 août 2011, a été adopté par le Comité Directeur le 10/04/2025 et présenté lors de l'Assemblée Générale Extraordinaire du 24/01/2026 avec avis favorable.</p>
        HTML;

    private const string REGLEMENT_LAC = <<<'HTML'
        <p>Arrêté préfectoral du 8 avril 1976 : règlement particulier de la police de la navigation de plaisance et des sports nautiques sur la retenue du barrage des Vieilles Forges.</p>
        <h2>Article 1 — Règles générales</h2>
        <p>Seules sont autorisées sur la retenue des Vieilles Forges les activités qui ne nuisent ni à la concession hydraulique accordée à EDF ni à l'alimentation en eau potable de la région du lac. Ces activités s'exercent aux risques et périls des pratiquants, sans engager la responsabilité d'EDF, du Département des Ardennes ni de l'Administration. Du fait des variations possibles du niveau de la retenue et de la présence d'obstacles immergés, les usagers prennent à leurs frais toutes précautions pour éviter accidents et avaries.</p>
        <h2>Article 2 — Circulation autorisée</h2>
        <p>La circulation des canoës-kayaks, des barques à rames et des canots pneumatiques, à l'exception des engins de plage, est libre sur toute la retenue en dehors des zones d'interdiction définies à l'article 8.</p>
        <h2>Article 3 — Voile</h2>
        <p>La navigation à voile est autorisée uniquement dans la zone centrale de la retenue, y compris un chenal d'accostage occasionnel au lieu dit la pile. Le périmètre de l'aire d'évolution de la voile est balisé par des bouées biconiques jaunes espacées tous les 100 m (50 m pour le chenal de la pile).</p>
        <h2>Article 4 — Pédalos</h2>
        <p>Seuls sont admis à circuler les pédalos autorisés par la convention prévue à l'article 12.</p>
        <h2>Article 5 — Baignades</h2>
        <p>Des baignades autorisées peuvent être aménagées en bordure de la retenue. Leurs limites sont matérialisées par des bouées rouges espacées tous les 20 m. Il est interdit aux baigneurs de franchir les bouées rouges qui ceinturent les baignades autorisées.</p>
        <h2>Article 6 — Sports motonautiques</h2>
        <p>La circulation de tout bateau à moteur et la pratique des sports motonautiques, notamment le ski nautique, sont interdites sur toute l'étendue de la retenue.</p>
        <h2>Article 7 — Plongée subaquatique</h2>
        <p>Les plongées subaquatiques sont interdites dans toute l'étendue de la retenue.</p>
        <h2>Article 8 — Zones interdites</h2>
        <p>La circulation et le stationnement de toute embarcation et engin flottant sont interdits : depuis la limite amont de l'emprise EDF jusqu'à une ligne balisée reliant deux panneaux d'interdiction plantés à terre sur chacune des rives à 700 m en aval du pont des Aulnes ; depuis une ligne balisée reliant deux panneaux plantés sur chaque rive à 200 m en amont du barrage, jusqu'au barrage ; à l'intérieur des zones de baignades délimitées par des bouées rouges.</p>
        <h2>Article 9 — Marques d'identité</h2>
        <p>Toute embarcation circulant ou stationnant sur la retenue doit porter les marques d'identification conformes à la réglementation générale de police en vigueur.</p>
        <h2>Article 10 — Stationnement</h2>
        <p>Le stationnement de toute embarcation sur fiches est interdit. Seul l'amarrage sur corps morts est autorisé. Les corps morts doivent être repérés par des bouées blanches reliées aux corps morts par chaînes, émergeant d'au moins 0,25 m.</p>
        <h2>Article 11 — Règles de route</h2>
        <p>Afin de réduire la gêne apportée aux pêcheurs, les embarcations ne doivent pas circuler, sauf cas de force majeure, à moins de 50 m des rives de la retenue. L'ordre de priorité pour la navigation est : bateaux de sécurité, bateaux à voile, embarcations légères. Dans chaque catégorie, l'embarcation la plus lente a priorité sur la plus rapide.</p>
        <h2>Article 12 — Louage</h2>
        <p>La location de pédalos et d'embarcations à des fins commerciales doit faire l'objet d'une convention préalable avec le Département des Ardennes. L'organisation de tout service de transport en commun de passagers est interdite.</p>
        <h2>Article 13 — Balisage</h2>
        <p>La signalisation, par panneaux et par bouées, est mise en place par le Département des Ardennes, à l'exclusion de l'entretien des bouées délimitant les zones de voile et de baignade qui incombe aux associations ou groupements bénéficiaires.</p>
        <h2>Article 14 — Dérogations</h2>
        <p>Des dérogations spéciales peuvent être accordées par arrêtés préfectoraux à l'occasion des fêtes, meetings, régates, courses, rassemblements ou essais de bateaux. Les interdictions ne sont pas opposables aux embarcations d'EDF, du Syndicat des eaux, ni à celles utilisées pour le contrôle de la pêche, le sauvetage, la sécurité des écoles de voile et des régates, dans l'exercice de leur mission. La vitesse des embarcations à moteur utilisées pour ces missions est limitée à 10 km/h, sauf cas de force majeure.</p>
        <h2>Article 15 — Infractions</h2>
        <p>Les infractions aux présentes dispositions sont constatées et réprimées conformément aux lois et règlements en vigueur.</p>
        <h2>Articles 16 et 17 — Texte abrogé, publication</h2>
        <p>L'arrêté du 17 juillet 1975 relatif à la réglementation de la baignade du lac des Vieilles Forges est abrogé. Le présent arrêté est publié au recueil des actes administratifs du Département des Ardennes et affiché par les maires des communes riveraines (Renwez, Sécheval, les Mazures, Harcy et Bourg-Fidèle).</p>
        <h2>Zones interdites à la navigation (plan annexé)</h2>
        <p>Le plan annexé à l'arrêté délimite les zones interdites : la partie du lac en aval d'une ligne située à 700 m en aval du pont des Aulnes, la bande des 200 m en amont du barrage jusqu'au barrage, et l'intérieur des zones de baignade délimitées par des bouées rouges. La zone centrale du lac est la zone autorisée pour la voile.</p>
        HTML;
}
