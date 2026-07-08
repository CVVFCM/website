<?php

declare(strict_types=1);

namespace App\AI\Chat;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * Streaming chat bound to one conversation store. Replaces the component's Chat:
 * its ChatStreamListener only buffers TextDeltas of the FIRST model response, so on
 * a tool-call turn (final text arrives through the toolbox's inner stream) it would
 * persist an empty assistant message — which the platform then sends back as
 * {"content": null} without tool_calls, a hard 400 on Mistral. Here the text is
 * accumulated from the deltas actually yielded to the consumer, which do include
 * the post-tool-call stream.
 */
final readonly class ForgieChat
{
    public function __construct(
        private AgentInterface $agent,
        private ForgieMessageStore $store,
        private ForgieConversationContext $context,
    ) {
    }

    /**
     * $message is what the model sees this turn (it may carry an attached image).
     * $persistedMessage, when given, is stored in its place — used to keep image
     * bytes out of the history (replaced by a text placeholder) so they are never
     * re-sent to the model on later turns.
     *
     * @return \Generator<DeltaInterface>
     */
    public function stream(UserMessage $message, ?UserMessage $persistedMessage = null): \Generator
    {
        $messages = $this->store->load();
        $messages->add($message);

        // Expose the live bag (incl. the current turn) to tools that need the verbatim.
        $this->context->set($messages);

        // Tool-call turns mutate $messages in place (toolbox appends the assistant
        // tool_calls + tool result messages), so they end up persisted too.
        $result = $this->agent->call($messages, ['stream' => true]);
        \assert($result instanceof StreamResult);

        $text = '';
        foreach ($result->getContent() as $delta) {
            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();
            }

            yield $delta;
        }

        if ('' !== $text) {
            $messages->add(Message::ofAssistant($text));
        }

        $this->store->save($this->toPersistedBag($messages, $message, $persistedMessage));
    }

    /**
     * Swaps the turn's user message for its persisted form (identity match) while
     * preserving every other message — including the tool_call/tool_result messages
     * the toolbox appended in place during the call.
     */
    private function toPersistedBag(MessageBag $messages, UserMessage $message, ?UserMessage $persistedMessage): MessageBag
    {
        if (null === $persistedMessage || $persistedMessage === $message) {
            return $messages;
        }

        $swapped = array_map(
            static fn (MessageInterface $current): MessageInterface => $current === $message ? $persistedMessage : $current,
            $messages->getMessages(),
        );

        return new MessageBag(...$swapped);
    }
}
