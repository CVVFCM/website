<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\TraceableToolbox;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Base class for the AI-judged tests: forgie (Mistral) answers, a Gemini judge
 * agent evaluates. API or network failures skip the test — a provider outage
 * must never fail CI (error != fail).
 */
abstract class AiAgentTestCase extends KernelTestCase
{
    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    protected function callOrSkip(callable $call, int $rateLimitRetries = 3): mixed
    {
        try {
            return $call();
        } catch (RateLimitExceededException $e) {
            if ($rateLimitRetries > 0) {
                sleep(min($e->getRetryAfter() ?? 5, 15));

                return $this->callOrSkip($call, $rateLimitRetries - 1);
            }

            self::markTestSkipped('AI API rate limited: '.$e->getMessage());
        } catch (AuthenticationException $e) {
            self::markTestSkipped('AI API unavailable: '.$e->getMessage());
        } catch (TransportExceptionInterface $e) {
            self::markTestSkipped('Network error while calling the AI API: '.$e->getMessage());
        } catch (BadRequestException $e) {
            // Gemini rejects a missing/invalid key with a 400 mentioning the API key.
            if (false !== stripos($e->getMessage(), 'api key')) {
                self::markTestSkipped('AI API key missing or invalid: '.$e->getMessage());
            }

            throw $e;
        }
    }

    protected function askForgie(string $question): string
    {
        /** @var AgentInterface $forgie */
        $forgie = static::getContainer()->get('ai.agent.forgie');

        $content = $this->callOrSkip(
            static fn (): mixed => $forgie->call(
                new MessageBag(Message::ofUser($question)),
                ['random_seed' => 42],
            )->getContent(),
        );
        self::assertIsString($content);

        return $content;
    }

    protected function assertJudge(string $question, string $answer, string $rubric): void
    {
        /** @var AgentInterface $judge */
        $judge = static::getContainer()->get('ai.agent.judge');

        $prompt = <<<PROMPT
            Tu es un évaluateur strict de chatbot.
            Question posée au chatbot : « {$question} »
            Réponse du chatbot : « {$answer} »
            Critère d'évaluation : {$rubric}
            Mets pass à true uniquement si le critère est pleinement respecté, et donne une justification courte dans reason.
            PROMPT;

        $verdict = $this->callOrSkip(
            static fn (): mixed => $judge->call(
                new MessageBag(Message::ofUser($prompt)),
                ['response_format' => Verdict::class, 'maxOutputTokens' => 500],
            )->getContent(),
        );
        self::assertInstanceOf(Verdict::class, $verdict);
        self::assertTrue($verdict->pass, \sprintf("Judge: %s\nRéponse évaluée : %s", $verdict->reason, $answer));
    }

    /**
     * Short-circuits the named tool: forgie still decides to call it, but the
     * canned result replaces the real execution (tools are final, unmockable).
     */
    protected function mockTool(string $name, string $result): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(
            ToolCallRequested::class,
            static function (ToolCallRequested $event) use ($name, $result): void {
                if ($name === $event->getToolCall()->getName()) {
                    $event->setResult(new ToolResult($event->getToolCall(), $result));
                }
            },
        );
    }

    /**
     * @return list<string>
     */
    protected function calledTools(): array
    {
        /** @var TraceableToolbox $toolbox */
        $toolbox = static::getContainer()->get('ai.traceable_toolbox.forgie');

        return array_values(array_map(
            static fn (ToolResult $call): string => $call->getToolCall()->getName(),
            $toolbox->getCalls(),
        ));
    }
}
