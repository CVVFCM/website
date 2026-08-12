<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent;

use PHPUnit\Framework\Attributes\Group;

/**
 * Forgie must not answer a weather question with a bulletin: every weather answer carries a short
 * verdict on what the conditions are worth for sailing, graded on wind force then wind direction
 * (see the "Conditions de navigation" section of config/prompts/forgie.md).
 *
 * The weather tool is short-circuited so each case pins one point of the scale.
 */
#[Group('ai')]
final class ForgieSailingAdviceTest extends AiAgentTestCase
{
    public function testIdealWindIsCalledIdealAndNamesTheLakeAxis(): void
    {
        $this->mockTool('weather_forecast', 'Prévision pour demain matin : vent 8 nœuds d\'est, 19°C, ensoleillé.');
        $question = 'Quelle est la météo prévue au lac demain matin ?';

        $answer = $this->askForgie($question);

        $this->assertJudge(
            $question,
            $answer,
            'La réponse présente ces conditions comme idéales ou très favorables pour naviguer, et'
            .' dit quelque chose de la direction est — au choix : qu\'elle entre dans l\'axe du lac,'
            .' qu\'elle vient de la route ou du pont des Aulnes, ou que c\'est la meilleure'
            .' orientation pour ce plan d\'eau.',
        );
    }

    public function testStrongNorthWindFlagsBothTheForceAndTheShiftyDirection(): void
    {
        $this->mockTool('live_weather', 'Station du club : vent moyen 22 nœuds de nord, rafales 27 nœuds, 16°C.');
        $question = 'Quel vent fait-il en ce moment au club ?';

        $answer = $this->askForgie($question);

        $this->assertJudge(
            $question,
            $answer,
            'La réponse signale DEUX choses : que 22 nœuds est un vent fort, réservé aux marins'
            .' expérimentés ou aux amateurs de planning, et que le vent de nord pose problème sur ce'
            .' lac — peu importe le mot employé : instable, difficile, défavorable, sans entrée'
            .' géographique, plein de risées ou de refus conviennent tous.',
        );
    }

    public function testAboveTwentyFiveKnotsItWarnsWithoutClosingTheWater(): void
    {
        $this->mockTool('live_weather', 'Station du club : vent moyen 28 nœuds de sud-ouest, rafales 34 nœuds, 14°C.');
        $question = 'Il y a du vent aujourd\'hui ?';

        $answer = $this->askForgie($question);

        $this->assertJudge(
            $question,
            $answer,
            'La réponse réserve ces conditions aux navigateurs de très bon niveau et conseille de'
            .' ne pas partir seul sur l\'eau ou de ne pas y aller sans être sûr de soi.'
            .' Elle n\'affirme jamais que le club a fermé le plan d\'eau ou interdit la navigation.',
        );
    }

    public function testNoWindIsCalledOutAsSuch(): void
    {
        $this->mockTool('weather_forecast', 'Prévision pour cet après-midi : vent 3 nœuds de sud, 24°C, ciel dégagé.');
        $question = 'Quel temps cet après-midi au lac ?';

        $answer = $this->askForgie($question);

        $this->assertJudge(
            $question,
            $answer,
            'La réponse indique qu\'il n\'y a quasiment pas de vent et que cela ne permet pas une'
            .' navigation soutenue, tout en restant utilisable pour une initiation ou une sortie calme.',
        );
    }

    public function testTheAdviceFollowsTheConversationLanguage(): void
    {
        $this->mockTool('weather_forecast', 'Prévision pour demain : vent 16 nœuds d\'est, 18°C.');
        $question = 'What is the weather forecast at the lake tomorrow?';

        $answer = $this->askForgie($question);

        $this->assertJudge(
            $question,
            $answer,
            'La réponse est intégralement rédigée en anglais et donne un avis sur la navigation'
            .' (conditions sportives, adaptées aux marins à l\'aise). Les noms de lieux français'
            .' éventuellement cités (Aulnes, Harcy, barrage) restent en français.',
        );
    }

    public function testAQuestionWithoutWeatherGetsNoSailingVerdict(): void
    {
        $question = 'Combien coûte l\'adhésion adulte pour une saison ?';

        $answer = $this->askForgie($question);

        $this->assertJudge(
            $question,
            $answer,
            'La réponse porte uniquement sur le tarif demandé et ne contient aucun commentaire sur'
            .' la météo, la force du vent ou les conditions de navigation.',
        );
    }
}
