<?php

declare(strict_types=1);

namespace App\Tests\AI\Notification;

use App\AI\Notification\ForgieContactNotification;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Notifier\Recipient\Recipient;

final class ForgieContactNotificationTest extends TestCase
{
    public function testAsEmailMessageBuildsATemplatedEmail(): void
    {
        $message = $this->notification()->asEmailMessage(new Recipient('contact@cvvfcm.fr'));

        $this->assertNotNull($message);
        $email = $message->getMessage();
        $this->assertInstanceOf(TemplatedEmail::class, $email);
        $this->assertSame('emails/forgie_contact.html.twig', $email->getHtmlTemplate());
        $this->assertSame('[Forgie] Nouveau contact — Jean Dupont', $email->getSubject());
        $this->assertSame('contact@cvvfcm.fr', $email->getTo()[0]->getAddress());
        $this->assertSame('jean@example.com', $email->getReplyTo()[0]->getAddress());

        $context = $email->getContext();
        $this->assertSame('Réservation stage été', $context['summary']);
        $this->assertSame('Jean', $context['firstName']);
        $this->assertStringContainsString('Visiteur : Bonjour', (string) $context['verbatim']);
    }

    public function testAsChatMessageContainsSummaryAndCoordinates(): void
    {
        $chat = $this->notification()->asChatMessage(new Recipient('contact@cvvfcm.fr'));

        $this->assertNotNull($chat);
        $subject = $chat->getSubject();
        $this->assertStringContainsString('Réservation stage été', $subject);
        $this->assertStringContainsString('Jean Dupont — jean@example.com', $subject);
        $this->assertStringContainsString('+33612345678', $subject);
        $this->assertStringContainsString('Visiteur : Bonjour', $subject);
    }

    public function testAsChatMessageConvertsMarkdownBoldToGoogleChatBold(): void
    {
        $notification = new ForgieContactNotification(
            'CVVFCM <contact@cvvfcm.fr>',
            'Résumé',
            'Jean',
            'Dupont',
            'jean.dupont@voile.fr',
            null,
            '**Visiteur :** Salut',
        );

        $chat = $notification->asChatMessage(new Recipient('contact@cvvfcm.fr'));

        $this->assertNotNull($chat);
        $this->assertStringContainsString('*Visiteur :* Salut', $chat->getSubject());
        $this->assertStringNotContainsString('**Visiteur :**', $chat->getSubject());
    }

    public function testAsEmailMessageAttachesTheImageWhenPresent(): void
    {
        $notification = new ForgieContactNotification(
            'CVVFCM <contact@cvvfcm.fr>',
            'Résumé',
            'Jean',
            'Dupont',
            'jean.dupont@voile.fr',
            null,
            'verbatim',
            ['email', 'chat/googlechat'],
            'IMGBYTES',
            'image/png',
            'photo.png',
        );

        $message = $notification->asEmailMessage(new Recipient('contact@cvvfcm.fr'));
        $this->assertNotNull($message);
        $email = $message->getMessage();
        $this->assertInstanceOf(TemplatedEmail::class, $email);
        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('IMGBYTES', $attachments[0]->getBody());
        $this->assertTrue($email->getContext()['hasAttachment']);

        $chat = $notification->asChatMessage(new Recipient('contact@cvvfcm.fr'));
        $this->assertNotNull($chat);
        $this->assertStringContainsString('Image jointe (voir email).', $chat->getSubject());
    }

    public function testAsChatMessageHasNoAttachmentNoteWithoutImage(): void
    {
        $chat = $this->notification()->asChatMessage(new Recipient('contact@cvvfcm.fr'));

        $this->assertNotNull($chat);
        $this->assertStringNotContainsString('Image jointe', $chat->getSubject());
    }

    private function notification(): ForgieContactNotification
    {
        return new ForgieContactNotification(
            'CVVFCM <contact@cvvfcm.fr>',
            'Réservation stage été',
            'Jean',
            'Dupont',
            'jean@example.com',
            '+33612345678',
            "Visiteur : Bonjour\nForgie : Bonjour ! Comment puis-je aider ?",
        );
    }
}
