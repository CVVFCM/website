<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AskForgie;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Streams Forgie's answer over Mercure: one {delta} update per token chunk,
 * then {done}. On failure an {error} update is published instead of retrying
 * (a retry would replay already-streamed chunks to the client).
 */
#[AsMessageHandler]
final readonly class AskForgieHandler
{
    public function __construct(
        private AgentInterface $agent,
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AskForgie $message): void
    {
        $topic = self::topic($message->conversationId);

        try {
            $result = $this->agent->call(
                new MessageBag(Message::ofUser($message->message)),
                ['stream' => true],
            );

            if ($result instanceof StreamResult) {
                foreach ($result->getContent() as $delta) {
                    if ($delta instanceof TextDelta) {
                        $this->publish($topic, ['delta' => $delta->getText()]);
                    }
                }
            } else {
                // Defensive fallback: model/platform answered without streaming.
                $content = $result->getContent();
                if (\is_string($content)) {
                    $this->publish($topic, ['delta' => $content]);
                }
            }

            $this->publish($topic, ['done' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Forgie failed to answer', ['exception' => $e, 'conversationId' => $message->conversationId]);
            $this->publish($topic, ['error' => true]);
        }
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
