<?php

declare(strict_types=1);

namespace App\AI\Notification;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A contact request raised by Forgie (SendContactMessageTool): sent to the club
 * admins on two channels — email (rendered from a Twig template) and the admins'
 * Google Chat space. Carries the request summary, the contact's coordinates and
 * the exact conversation verbatim.
 */
final class ForgieContactNotification extends Notification implements EmailNotificationInterface, ChatNotificationInterface
{
    /**
     * @param list<string> $channels
     */
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $summary,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly string $verbatim,
        private readonly array $channels = ['email', 'chat/googlechat'],
    ) {
        parent::__construct(\sprintf('[Forgie] Nouveau contact — %s %s', $this->firstName, $this->lastName));
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getChannels(RecipientInterface $recipient): array
    {
        return $this->channels;
    }

    #[\Override]
    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $email = new TemplatedEmail()
            ->from($this->fromAddress)
            ->to($recipient->getEmail())
            ->replyTo($this->email)
            ->subject($this->getSubject())
            ->htmlTemplate('emails/forgie_contact.html.twig')
            ->context([
                'summary' => $this->summary,
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                // 'email' is a reserved TemplatedEmail context variable.
                'contactEmail' => $this->email,
                'phone' => $this->phone,
                'verbatim' => $this->verbatim,
            ])
        ;

        return new EmailMessage($email);
    }

    #[\Override]
    public function asChatMessage(RecipientInterface $recipient, ?string $transport = null): ?ChatMessage
    {
        $coordinates = \sprintf('%s %s — %s', $this->firstName, $this->lastName, $this->email);
        if (null !== $this->phone && '' !== $this->phone) {
            $coordinates .= ' — '.$this->phone;
        }

        $text = \sprintf(
            "*Nouveau contact via Forgie*\n\n*Demande :* %s\n*Coordonnées :* %s\n\n*Conversation :*\n%s",
            $this->summary,
            $coordinates,
            $this->verbatim,
        );

        return new ChatMessage($this->toGoogleChatFormatting($text));
    }

    /**
     * Google Chat uses single-asterisk bold (*bold*), not Markdown's **bold**.
     */
    private function toGoogleChatFormatting(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text) ?? $text;
    }
}
