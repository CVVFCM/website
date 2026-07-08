<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\AI\Chat\ForgieChatFactory;
use App\AI\Chat\ForgieConversationContext;
use App\AI\Service\ForgieImagePreparer;
use App\Entity\ForgieUpload;
use App\Message\AskForgie;
use App\Repository\ForgieUploadRepository;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Streams Forgie's answer over Mercure: one {delta} update per token chunk,
 * then {done}. On failure an {error} update is published instead of retrying
 * (a retry would replay already-streamed chunks to the client).
 *
 * Conversation memory: the Chat loads the history stored for this conversationId
 * and persists the new user + assistant messages once the stream completes.
 */
#[AsMessageHandler]
final readonly class AskForgieHandler
{
    public function __construct(
        private ForgieChatFactory $chats,
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ForgieUploadRepository $uploads,
        private ForgieConversationContext $context,
        private ForgieImagePreparer $imagePreparer,
    ) {
    }

    public function __invoke(AskForgie $message): void
    {
        $topic = self::topic($message->conversationId);

        try {
            $chat = $this->chats->create($message->conversationId);

            // Reset every turn (shared singleton): the tool must never see a previous
            // turn's image, and it needs this one to attach it to the admin email.
            $upload = $this->resolveUpload($message);
            $this->context->setUpload($upload);

            [$vision, $persisted] = $this->buildMessages($message->message, $upload);

            foreach ($chat->stream($vision, $persisted) as $delta) {
                if ($delta instanceof TextDelta) {
                    $this->publish($topic, ['delta' => $delta->getText()]);
                }
            }

            $this->publish($topic, ['done' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Forgie failed to answer', ['exception' => $e, 'conversationId' => $message->conversationId]);
            $this->publish($topic, ['error' => true]);
        }
    }

    private function resolveUpload(AskForgie $message): ?ForgieUpload
    {
        if (null === $message->uploadId) {
            return null;
        }

        $upload = $this->uploads->find($message->uploadId);
        if (null === $upload || $upload->conversationId !== $message->conversationId) {
            $this->logger->warning('Forgie ignored an unknown or mismatched upload', [
                'uploadId' => $message->uploadId,
                'conversationId' => $message->conversationId,
            ]);

            return null;
        }

        return $upload;
    }

    /**
     * Builds the message the model sees (with the image, if any) and, when an image is
     * present, the placeholder message stored in its place so the bytes never persist.
     *
     * @return array{0: UserMessage, 1: UserMessage|null}
     */
    private function buildMessages(string $text, ?ForgieUpload $upload): array
    {
        if (null === $upload) {
            return [Message::ofUser($text), null];
        }

        $image = $this->imagePreparer->toModelImage($upload);
        // A text-less user turn (image only) leaves the model without an utterance to
        // answer, so it falls back to "Je ne sais pas". Give it a neutral anchor.
        $visionText = '' !== $text ? $text : 'Le visiteur a envoyé cette image, sans autre message.';
        $vision = Message::ofUser($visionText, $image);
        $persisted = Message::ofUser(trim($text.' [Image envoyée : '.$upload->filename.']'));

        return [$vision, $persisted];
    }

    public static function topic(string $conversationId): string
    {
        return '/forgie/conversations/'.$conversationId;
    }

    /**
     * @param array<string, bool|string> $data
     */
    private function publish(string $topic, array $data): void
    {
        $this->hub->publish(new Update($topic, json_encode($data, \JSON_THROW_ON_ERROR)));
    }
}
