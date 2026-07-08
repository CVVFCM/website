<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\AI\Chat\ForgieChatFactory;
use App\AI\Chat\ForgieConversationContext;
use App\AI\Service\ForgieImagePreparer;
use App\Entity\ForgieUpload;
use App\Message\AskForgie;
use App\MessageHandler\AskForgieHandler;
use App\Repository\ForgieUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class AskForgieHandlerTest extends TestCase
{
    private const string UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d251';
    private const string TOPIC = '/forgie/conversations/019779c9-2f74-7a3e-8bcb-1d6c02f0d251';
    private const string UPLOAD_UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d999';
    // 1×1 transparent PNG.
    private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function testItStreamsDeltasThenDone(): void
    {
        $agent = $this->agent(new TextDelta('Bon'), new TextDelta('jour'), new TextDelta(' !'));

        $updates = [];
        $this->handler($agent, $updates)(new AskForgie(self::UUID, 'Salut'));

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
        $this->handler($agent, $updates)(new AskForgie(self::UUID, 'Salut'));

        $this->assertSame([[self::TOPIC, '{"error":true}']], $updates);
    }

    public function testItShowsTheUploadedImageToTheModelAndExposesItToTools(): void
    {
        $captured = null;
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function (MessageBag $messages) use (&$captured): StreamResult {
            $captured = $messages;

            return new StreamResult((static function (): \Generator {
                yield new TextDelta('ok');
            })());
        });

        $upload = $this->upload(self::UUID);
        $context = new ForgieConversationContext();
        $updates = [];
        $this->handler($agent, $updates, $upload, $context)(new AskForgie(self::UUID, 'Regarde ça', self::UPLOAD_UUID));

        // The tool can pick the image up for the current turn.
        $this->assertSame($upload, $context->getUpload());

        // The model saw the image (the bag gains an assistant message after the call,
        // so look for the image-bearing user message rather than assuming it is last).
        $this->assertInstanceOf(MessageBag::class, $captured);
        $withImage = array_filter(
            $captured->getMessages(),
            static fn (object $m): bool => $m instanceof UserMessage && $m->hasImageContent(),
        );
        $this->assertCount(1, $withImage);
    }

    public function testItAnchorsAnImageOnlyMessageWithText(): void
    {
        // A text-less image turn must still carry a user utterance, else the model
        // has nothing to answer and falls back to "Je ne sais pas".
        $captured = null;
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function (MessageBag $messages) use (&$captured): StreamResult {
            $captured = $messages;

            return new StreamResult((static function (): \Generator {
                yield new TextDelta('ok');
            })());
        });

        $updates = [];
        $this->handler($agent, $updates, $this->upload(self::UUID), new ForgieConversationContext())(new AskForgie(self::UUID, '', self::UPLOAD_UUID));

        $this->assertInstanceOf(MessageBag::class, $captured);
        $withImage = array_values(array_filter(
            $captured->getMessages(),
            static fn (object $m): bool => $m instanceof UserMessage && $m->hasImageContent(),
        ));
        $this->assertCount(1, $withImage);
        $this->assertInstanceOf(UserMessage::class, $withImage[0]);
        $this->assertNotSame('', trim((string) $withImage[0]->asText()));
    }

    public function testItIgnoresAnUploadFromAnotherConversation(): void
    {
        $agent = $this->agent(new TextDelta('ok'));
        $upload = $this->upload('019779c9-2f74-7a3e-8bcb-1d6c02f0dAAA');
        $context = new ForgieConversationContext();
        $updates = [];

        $this->handler($agent, $updates, $upload, $context)(new AskForgie(self::UUID, 'Regarde', self::UPLOAD_UUID));

        $this->assertNull($context->getUpload());
        $this->assertSame([self::TOPIC, '{"done":true}'], $updates[array_key_last($updates)]);
    }

    private function agent(TextDelta ...$deltas): AgentInterface
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn(new StreamResult((static function () use ($deltas): \Generator {
            yield from $deltas;
        })()));

        return $agent;
    }

    /**
     * @param list<array{string, string}> $updates
     */
    private function handler(AgentInterface $agent, array &$updates, ?ForgieUpload $upload = null, ?ForgieConversationContext $context = null): AskForgieHandler
    {
        $context ??= new ForgieConversationContext();

        return new AskForgieHandler(
            $this->factory($agent, $context),
            $this->hub($updates),
            new NullLogger(),
            $this->uploads($upload),
            $context,
            new ForgieImagePreparer(new NullLogger()),
        );
    }

    /**
     * Real repository whose find() delegates to a stubbed EntityManager (the repo is
     * final and cannot be doubled directly).
     */
    private function uploads(?ForgieUpload $upload): ForgieUploadRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(ForgieUpload::class));
        $entityManager->method('find')->willReturn($upload);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new ForgieUploadRepository($registry);
    }

    /**
     * Real factory + store over a stubbed EntityManager: the Chat wraps the agent
     * stream, an unknown conversation loads an empty bag, persistence is flushed once
     * the stream completes. Shares the context so the handler and the Chat agree.
     */
    private function factory(AgentInterface $agent, ForgieConversationContext $context): ForgieChatFactory
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        return new ForgieChatFactory($agent, $entityManager, $context);
    }

    private function upload(string $conversationId): ForgieUpload
    {
        return new ForgieUpload(self::UPLOAD_UUID, $conversationId, self::PNG_BASE64, 'image/png', 'photo.png', 70, new \DateTimeImmutable());
    }

    /**
     * @param list<array{string, string}> $updates
     */
    private function hub(array &$updates): HubInterface
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willReturnCallback(function (Update $update) use (&$updates): string {
            $updates[] = [$update->getTopics()[0], $update->getData()];

            return 'id';
        });

        return $hub;
    }
}
