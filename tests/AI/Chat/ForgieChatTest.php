<?php

declare(strict_types=1);

namespace App\Tests\AI\Chat;

use App\AI\Chat\ForgieChat;
use App\AI\Chat\ForgieConversationContext;
use App\AI\Chat\ForgieMessageStore;
use App\Entity\ForgieConversation;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\StreamResult;

final class ForgieChatTest extends TestCase
{
    public function testItPersistsTheFullStreamedTextAsAssistantMessage(): void
    {
        // Interleaved non-text delta mimics a tool-call turn: the text after it must
        // be accumulated too (the component's ChatStreamListener lost it, causing an
        // empty assistant message and a Mistral 400 on the next turn).
        $store = $this->createStore();
        $context = new ForgieConversationContext();
        $chat = new ForgieChat($this->createAgent(
            new TextDelta('Bon'),
            new ToolCallStart('id', 'live_weather'),
            new TextDelta('jour !'),
        ), $store, $context);

        $deltas = iterator_to_array($chat->stream(Message::ofUser('Salut')), false);

        $this->assertCount(3, $deltas);

        // The live bag (incl. the current user turn) is exposed for tools.
        $this->assertNotNull($context->get());
        $this->assertSame('Salut', $context->get()->getMessages()[0]->asText());

        $messages = $store->load()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('Salut', $messages[0]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        $content = $messages[1]->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('Bonjour !', $content[0]->getText());
    }

    public function testItNeverPersistsAnEmptyAssistantMessage(): void
    {
        $store = $this->createStore();
        $chat = new ForgieChat($this->createAgent(new ToolCallStart('id', 'live_weather')), $store, new ForgieConversationContext());

        iterator_to_array($chat->stream(Message::ofUser('Salut')), false);

        $messages = $store->load()->getMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
    }

    private function createAgent(TextDelta|ToolCallStart ...$deltas): AgentInterface
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn(new StreamResult((static function () use ($deltas): \Generator {
            yield from $deltas;
        })()));

        return $agent;
    }

    /**
     * Real store over an EntityManager double acting as a one-row store, so the
     * persisted bag can be asserted through load().
     */
    private function createStore(): ForgieMessageStore
    {
        $row = new class {
            public ?ForgieConversation $conversation = null;
        };

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnCallback(static fn (): ?ForgieConversation => $row->conversation);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use ($row): void {
            \assert($entity instanceof ForgieConversation);
            $row->conversation = $entity;
        });

        return new ForgieMessageStore($entityManager, '019779c9-2f74-7a3e-8bcb-1d6c02f0d251');
    }
}
