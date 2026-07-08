<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Chat\ForgieConversationContext;
use App\AI\Tool\SendContactMessageTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final class SendContactMessageToolTest extends TestCase
{
    private const string ADMIN = 'CVVFCM <contact@cvvfcm.fr>';

    public function testItSendsOnEmailAndGoogleChatWhenCoordinatesAreComplete(): void
    {
        $notifier = $this->notifier();
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $result = $tool('Réservation stage été', 'Jean', 'Dupont', 'jean.dupont@voile.fr', '+33612345678');

        $this->assertSame('envoyé', $result['status']);
        // Each channel is dispatched on its own notification, always to the admin recipient.
        $this->assertCount(2, $notifier->notifications);
        $channels = array_merge(...array_map(static fn (Notification $n): array => $n->getChannels(new Recipient(self::ADMIN)), $notifier->notifications));
        $this->assertEqualsCanonicalizing(['email', 'chat/googlechat'], $channels);
        $this->assertEquals([new Recipient(self::ADMIN)], $notifier->recipients);
    }

    public function testTheNotificationCarriesTheVerbatim(): void
    {
        $notifier = $this->notifier();
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $tool('Réservation stage été', 'Jean', 'Dupont', 'jean.dupont@voile.fr');

        $notification = $notifier->notifications[0];

        // Chat: Google Chat single-asterisk bold, verbatim incl. the current turn (the
        // booking request) even though it is not yet persisted when the tool runs.
        $chat = $notification->asChatMessage(new Recipient(self::ADMIN));
        $this->assertNotNull($chat);
        $this->assertStringContainsString('*Visiteur :* Je veux réserver un stage', $chat->getSubject());
        $this->assertStringContainsString('*Forgie :* Bonjour ! Comment puis-je aider ?', $chat->getSubject());
        $this->assertStringContainsString('Réservation stage été', $chat->getSubject());

        // Email: verbatim kept as Markdown (** **) for markdown_to_html rendering.
        $message = $notification->asEmailMessage(new Recipient(self::ADMIN));
        $this->assertNotNull($message);
        $email = $message->getMessage();
        $this->assertInstanceOf(TemplatedEmail::class, $email);
        $this->assertStringContainsString('**Visiteur :** Je veux réserver un stage', (string) $email->getContext()['verbatim']);
    }

    public function testItRefusesToSendWithAnInvalidEmail(): void
    {
        $notifier = $this->notifier();
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $result = $tool('Réservation', 'Jean', 'Dupont', 'pas-un-email');

        $this->assertSame('incomplet', $result['status']);
        $this->assertCount(0, $notifier->notifications);
    }

    public function testItRefusesToSendWithAMissingName(): void
    {
        $notifier = $this->notifier();
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $result = $tool('Réservation', 'Jean', '   ', 'jean.dupont@voile.fr');

        $this->assertSame('incomplet', $result['status']);
        $this->assertCount(0, $notifier->notifications);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function placeholderCoordinates(): iterable
    {
        yield 'unknown names' => ['Inconnu', 'Inconnu', 'jean.dupont@voile.fr'];
        yield 'example domain' => ['Jean', 'Dupont', 'inconnu@exemple.com'];
    }

    #[DataProvider('placeholderCoordinates')]
    public function testItRefusesFabricatedPlaceholderCoordinates(string $firstName, string $lastName, string $email): void
    {
        $notifier = $this->notifier();
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $result = $tool('Réservation', $firstName, $lastName, $email);

        $this->assertSame('incomplet', $result['status']);
        $this->assertCount(0, $notifier->notifications);
    }

    public function testItStillSucceedsWhenOnlyGoogleChatFails(): void
    {
        // A Google Chat outage must not hide the email that already reached the admins.
        $notifier = new class implements NotifierInterface {
            #[\Override]
            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                if (\in_array('chat/googlechat', $notification->getChannels($recipients[0]), true)) {
                    throw new \RuntimeException('google chat down');
                }
            }
        };
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $result = $tool('Réservation', 'Jean', 'Dupont', 'jean.dupont@voile.fr');

        $this->assertSame('envoyé', $result['status']);
    }

    public function testItReturnsAnErrorWhenEveryChannelFails(): void
    {
        $notifier = new class implements NotifierInterface {
            #[\Override]
            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                throw new \RuntimeException('transport down');
            }
        };
        $tool = new SendContactMessageTool($notifier, $this->contextWithConversation(), new NullLogger(), self::ADMIN);

        $result = $tool('Réservation', 'Jean', 'Dupont', 'jean.dupont@voile.fr');

        $this->assertSame('erreur', $result['status']);
    }

    private function contextWithConversation(): ForgieConversationContext
    {
        $context = new ForgieConversationContext();
        $context->set(new MessageBag(
            Message::ofUser('Bonjour'),
            Message::ofAssistant('Bonjour ! Comment puis-je aider ?'),
            Message::ofUser('Je veux réserver un stage cet été'),
        ));

        return $context;
    }

    /**
     * @return NotifierInterface&object{notifications: list<Notification>, recipients: list<RecipientInterface>}
     */
    private function notifier(): NotifierInterface
    {
        return new class implements NotifierInterface {
            /** @var list<Notification> */
            public array $notifications = [];

            /** @var list<RecipientInterface> */
            public array $recipients = [];

            #[\Override]
            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->notifications[] = $notification;
                $this->recipients = array_values($recipients);
            }
        };
    }
}
