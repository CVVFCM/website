<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent;

use PHPUnit\Framework\Attributes\Group;

#[Group('ai')]
final class ForgieToolCallTest extends AiAgentTestCase
{
    public function testWeatherQuestionCallsWeatherForecast(): void
    {
        $this->mockTool('weather_forecast', 'Prévision pour demain : vent 12 nœuds de nord-ouest, 21°C, ensoleillé.');

        $answer = $this->askForgie('Quelle est la météo prévue au lac demain ?');

        self::assertContains('weather_forecast', $this->calledTools());
        self::assertStringContainsString('21', $answer);
    }

    public function testMeteoMaisonExactPhraseCallsHomeWeather(): void
    {
        $this->mockTool('home_weather', 'Météo maison : vent 8 nœuds, rafales 14 nœuds, fiabilité haute.');
        $this->mockTool('weather_forecast', 'Prévision : vent 12 nœuds de nord-ouest, 21°C.');

        $this->askForgie('Peux-tu me donner la météo maison ?');

        self::assertContains('home_weather', $this->calledTools());
    }

    public function testGenericWeatherQuestionDoesNotCallHomeWeather(): void
    {
        $this->mockTool('home_weather', 'Météo maison : vent 8 nœuds.');
        $this->mockTool('weather_forecast', 'Prévision : vent 12 nœuds de nord-ouest, 21°C.');

        $this->askForgie('Quel temps fera-t-il demain ?');

        self::assertNotContains('home_weather', $this->calledTools());
    }
}
