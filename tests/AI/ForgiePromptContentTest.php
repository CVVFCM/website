<?php

declare(strict_types=1);

namespace App\Tests\AI;

use PHPUnit\Framework\TestCase;

/**
 * Guards the sailing-advice section of the Forgie prompt against accidental loss.
 *
 * This is a file check, not a behaviour check: it cannot tell whether Forgie actually follows the
 * scale — only the AI-judged tests in tests/AI/Agent do, and they need API keys CI does not have.
 * What it does catch is the section being dropped or gutted while the prompt is edited for
 * something else, which no other test would notice.
 */
final class ForgiePromptContentTest extends TestCase
{
    private const string PROMPT = __DIR__.'/../../config/prompts/forgie.md';

    public function testTheSailingConditionsSectionIsPresent(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('## Conditions de navigation', $prompt);
        $this->assertMatchesRegularExpression(
            '/une phrase, deux au maximum/u',
            $prompt,
            'The one-or-two-sentence limit is what keeps the advice from turning into a bulletin.',
        );
    }

    public function testEveryWindBandIsDescribed(): void
    {
        $prompt = $this->prompt();

        foreach (['moins de 5', '5 à 10', '10 à 15', '15 à 20', '20 à 25', 'plus de 25'] as $band) {
            $this->assertStringContainsString('| '.$band.' |', $prompt, sprintf('Wind band "%s" is missing.', $band));
        }
    }

    public function testTheStrongWindWarningSurvives(): void
    {
        $prompt = $this->prompt();

        // Above 25 knots the answer must warn rather than authorise, and must never speak of the
        // club closing the water — that call belongs to the people on site, not to a chatbot.
        // Matched without the leading « ne » and without the surrounding emphasis markers, so the
        // guard survives a rewording of the sentence around it.
        $this->assertStringContainsString('partir seul sur l\'eau', $prompt);
        $this->assertStringContainsString('tu ne déclares jamais le plan d\'eau fermé', $prompt);
    }

    public function testEveryCardinalDirectionIsRated(): void
    {
        $prompt = $this->prompt();
        $section = substr($prompt, (int) strpos($prompt, '### Direction du vent'));

        foreach (['Est', 'Sud-Est', 'Sud', 'Sud-Ouest', 'Ouest', 'Nord-Ouest', 'Nord-Est', 'Nord'] as $direction) {
            $this->assertStringContainsString('| '.$direction.' |', $section, sprintf('Direction "%s" is unrated.', $direction));
        }
    }

    public function testTheClubLandmarksAreQuoted(): void
    {
        $prompt = $this->prompt();

        // Members name a wind by where it comes from; losing these turns the advice generic.
        foreach (['Aulnes', 'Harcy', 'barrage'] as $landmark) {
            $this->assertStringContainsString($landmark, $prompt, sprintf('Club landmark "%s" is missing.', $landmark));
        }
    }

    /**
     * Whitespace is collapsed so the assertions survive a reflow: the prompt is hard-wrapped at
     * eighty columns, and a sentence that reads as one line here may be split across two there.
     */
    private function prompt(): string
    {
        $prompt = file_get_contents(self::PROMPT);
        $this->assertIsString($prompt);

        return (string) preg_replace('/\s+/u', ' ', $prompt);
    }
}
