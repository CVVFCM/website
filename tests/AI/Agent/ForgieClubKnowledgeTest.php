<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent;

use PHPUnit\Framework\Attributes\Group;

#[Group('ai')]
final class ForgieClubKnowledgeTest extends AiAgentTestCase
{
    public function testPresidentQuestionUsesBoardMembers(): void
    {
        $this->mockTool('board_members', (string) json_encode([
            'membres' => [
                ['nom' => 'Yohan Giarelli', 'fonction' => 'Président', 'email' => 'yohan@cvvfcm.fr'],
                ['nom' => 'Baptiste Gilles-Carret', 'fonction' => 'Trésorier', 'email' => 'baptiste@cvvfcm.fr'],
            ],
        ]));

        $answer = $this->askForgie('Qui est le président du club ?');

        self::assertContains('board_members', $this->calledTools());
        // #92: the statutes describe how the board is elected, never who sits on it —
        // answering a "who is" question from `club_rules` is the regression to catch.
        self::assertNotContains('club_rules', $this->calledTools());
        self::assertStringContainsString('Yohan Giarelli', $answer);
    }

    public function testCommitteeSizeQuestionUsesClubRules(): void
    {
        $this->mockTool('club_rules', 'Statuts du CVVFCM — Article 12 : Le CVVFCM est administré par un Comité de Direction de 6 à 15 membres, élus pour quatre années.');

        $question = 'Combien de membres compte le comité de direction du club ?';
        $answer = $this->askForgie($question);

        self::assertContains('club_rules', $this->calledTools());
        $this->assertJudge(
            $question,
            $answer,
            'La réponse indique que le comité de direction compte de 6 à 15 membres. Mentionner les statuts est un plus mais n\'est pas obligatoire.',
        );
    }

    public function testWaterSkiQuestionUsesLakeRules(): void
    {
        $this->mockTool('club_rules', "Règlement de la police de la navigation du lac des Vieilles Forges (arrêté préfectoral du 08/04/1976) — Article 6 : La circulation de tout bateau à moteur et la pratique des sports motonautiques, notamment le ski nautique, sont interdites sur toute l'étendue de la retenue.");

        $question = 'Le ski nautique est-il autorisé sur le lac des Vieilles Forges ?';
        $answer = $this->askForgie($question);

        self::assertContains('club_rules', $this->calledTools());
        $this->assertJudge(
            $question,
            $answer,
            "La réponse indique que le ski nautique est interdit sur le lac. Mentionner le règlement ou l'arrêté préfectoral est un plus mais n'est pas obligatoire.",
        );
    }
}
