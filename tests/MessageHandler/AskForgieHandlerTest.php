<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\AI\Chat\ForgieChatFactory;
use App\Message\AskForgie;
use App\MessageHandler\AskForgieHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class AskForgieHandlerTest extends TestCase
{
    private const string UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d251';
    private const string TOPIC = '/forgie/conversations/019779c9-2f74-7a3e-8bcb-1d6c02f0d251';

    public function testItStreamsDeltasThenDone(): void
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn(new StreamResult((function (): \Generator {
            yield new TextDelta('Bon');
            yield new TextDelta('jour');
            yield new TextDelta(' !');
        })()));

        $updates = [];
        $hub = $this->createHub($updates);

        new AskForgieHandler($this->createFactory($agent), $hub, new NullLogger())(new AskForgie(self::UUID, 'Salut'));

        $this->assertSame([
            [self::TOPIC, '{"delta":"Bon"}'],
            [self::TOPIC, '{"delta":"jour"}'],
            [self::TOPIC, '{"delta":" !"}'],
            [self::TOPIC, '{"done":true}'],
        ], $updates);
    }

    public function testItPublishesAnErrorWhenTheAgentFails(): void
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willThrowException(new \RuntimeException('mistral down'));

        $updates = [];
        $hub = $this->createHub($updates);

        new AskForgieHandler($this->createFactory($agent), $hub, new NullLogger())(new AskForgie(self::UUID, 'Salut'));

        $this->assertSame([[self::TOPIC, '{"error":true}']], $updates);
    }

    /**
     * Real factory + store over a stubbed EntityManager: the Chat wraps the agent
     * stream, an unknown conversation loads an empty bag, persistence is flushed
     * once the stream completes.
     */
    private function createFactory(AgentInterface $agent): ForgieChatFactory
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        return new ForgieChatFactory($agent, $entityManager);
    }

    /**
     * @param list<array{string, string}> $updates
     */
    private function createHub(array &$updates): HubInterface
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willReturnCallback(function (Update $update) use (&$updates): string {
            $updates[] = [$update->getTopics()[0], $update->getData()];

            return 'id';
        });

        return $hub;
    }
}
