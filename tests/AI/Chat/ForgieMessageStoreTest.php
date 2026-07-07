<?php

declare(strict_types=1);

namespace App\Tests\AI\Chat;

use App\AI\Chat\ForgieMessageStore;
use App\Entity\ForgieConversation;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

final class ForgieMessageStoreTest extends TestCase
{
    private const string UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d251';

    public function testUnknownConversationLoadsAnEmptyBag(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $bag = (new ForgieMessageStore($entityManager, self::UUID))->load();

        $this->assertCount(0, $bag->getMessages());
    }

    public function testSaveThenLoadRoundTripsMessages(): void
    {
        $store = $this->storeOverInMemoryRow();

        $store->save(new MessageBag(
            Message::ofUser('Je m\'appelle Yohan'),
            Message::ofAssistant('Bienvenue Yohan !'),
        ));

        $messages = $store->load()->getMessages();

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('Je m\'appelle Yohan', $messages[0]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        // The component's MessageNormalizer denormalizes assistant content into
        // content parts (Text objects), not back into a plain string.
        $content = $messages[1]->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('Bienvenue Yohan !', $content[0]->getText());
    }

    public function testLoadCapsHistoryToTheMostRecentMessages(): void
    {
        $store = $this->storeOverInMemoryRow();

        $bag = new MessageBag();
        for ($i = 1; $i <= 25; ++$i) {
            $bag->add(Message::ofUser("message $i"));
        }
        $store->save($bag);

        $messages = $store->load()->getMessages();

        $this->assertCount(20, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('message 6', $messages[0]->asText());
        $this->assertInstanceOf(UserMessage::class, $messages[19]);
        $this->assertSame('message 25', $messages[19]->asText());
    }

    public function testLoadDropsEmptyAssistantMessages(): void
    {
        // Rows written by the component's buggy ChatStreamListener contain an
        // assistant message with an empty text: sent back to Mistral it becomes
        // {"content": null} without tool_calls — a hard 400.
        $store = $this->storeOverInMemoryRow();

        $store->save(new MessageBag(
            Message::ofUser('Quel temps ?'),
            Message::ofAssistant(''),
        ));

        $messages = $store->load()->getMessages();

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
    }

    public function testLoadStartsTheCappedHistoryAtAUserMessage(): void
    {
        // The cap may cut mid-exchange; a bag starting with an assistant (or orphan
        // tool result) message is rejected by the API, so leading non-user messages
        // are dropped.
        $store = $this->storeOverInMemoryRow();

        $bag = new MessageBag();
        for ($i = 1; $i <= 13; ++$i) {
            $bag->add(Message::ofUser("question $i"));
            if ($i < 13) {
                $bag->add(Message::ofAssistant("réponse $i"));
            }
        }
        $store->save($bag);

        $messages = $store->load()->getMessages();

        // 25 messages capped to 20 would start at "réponse 3": it is dropped too.
        $this->assertCount(19, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('question 4', $messages[0]->asText());
    }

    /**
     * EntityManager double acting as a one-row store: save() persists or mutates the
     * entity, find() returns whatever was last persisted.
     */
    private function storeOverInMemoryRow(): ForgieMessageStore
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

        return new ForgieMessageStore($entityManager, self::UUID);
    }
}
