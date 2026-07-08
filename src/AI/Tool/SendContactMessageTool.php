<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Chat\ForgieConversationContext;
use App\AI\Notification\ForgieContactNotification;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Lets Forgie escalate a request to the club admins (booking, rental, or any
 * question needing a human follow-up) by sending them a message on two channels
 * (email + Google Chat), with a summary, the contact's coordinates and the exact
 * conversation verbatim. The verbatim is read from the live conversation bag
 * (ForgieConversationContext) so it includes the current turn, which is not yet
 * persisted when the tool runs.
 */
#[AsTool('send_contact_message', 'Envoie un message aux responsables du club (email + espace de discussion des admins) pour une réservation (stage, location), une demande d\'information particulière, ou toute demande nécessitant un suivi humain. IMPÉRATIF : n\'appeler cet outil QUE lorsque la personne a réellement fourni son prénom, son nom et son email ; ne JAMAIS inventer ni mettre de valeurs génériques (« Inconnu », « exemple.com »…). S\'il manque une de ces informations, ne pas appeler l\'outil : la demander d\'abord. Le téléphone est optionnel. Résumer la demande dans "summary".')]
final readonly class SendContactMessageTool
{
    public function __construct(
        private NotifierInterface $notifier,
        private ForgieConversationContext $context,
        private LoggerInterface $logger,
        #[Autowire('%env(SULU_ADMIN_EMAIL)%')]
        private string $adminEmail,
    ) {
    }

    /**
     * @return array{status: string, message?: string}
     */
    public function __invoke(
        #[Schema(description: 'Résumé clair et concis de la demande du contact.')]
        string $summary,
        #[Schema(description: 'Prénom du contact.')]
        string $firstName,
        #[Schema(description: 'Nom du contact.')]
        string $lastName,
        #[Schema(description: 'Adresse email du contact.')]
        string $email,
        #[Schema(description: 'Numéro de téléphone du contact (optionnel).')]
        ?string $phone = null,
    ): array {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);

        if ('' === $firstName || '' === $lastName) {
            return ['status' => 'incomplet', 'message' => 'Demande le prénom et le nom du contact avant d\'envoyer.'];
        }

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'incomplet', 'message' => 'Demande une adresse email valide avant d\'envoyer.'];
        }

        // The model sometimes fabricates placeholder coordinates instead of asking the
        // visitor; reject the obvious ones so nothing is sent without real data.
        if ($this->looksLikePlaceholder($firstName, $lastName, $email)) {
            return ['status' => 'incomplet', 'message' => 'Ne devine pas les coordonnées : demande à la personne son vrai prénom, nom et email avant d\'envoyer.'];
        }

        $phone = null !== $phone ? trim($phone) : null;
        $verbatim = $this->verbatim();
        $attachment = $this->attachment();

        // Dispatch each channel on its own so a Google Chat outage never hides the fact
        // that the admins were already reached by email (and vice versa).
        $emailSent = $this->dispatch(['email'], $summary, $firstName, $lastName, $email, $phone, $verbatim, $attachment);
        $chatSent = $this->dispatch(['chat/googlechat'], $summary, $firstName, $lastName, $email, $phone, $verbatim, $attachment);

        if (!$emailSent && !$chatSent) {
            return ['status' => 'erreur', 'message' => 'L\'envoi a échoué. Invite la personne à réessayer plus tard ou à écrire à contact@cvvfcm.fr.'];
        }

        return ['status' => 'envoyé'];
    }

    /**
     * @param list<string>                                          $channels
     * @param array{bytes: string, mime: string, name: string}|null $attachment
     */
    private function dispatch(array $channels, string $summary, string $firstName, string $lastName, string $email, ?string $phone, string $verbatim, ?array $attachment): bool
    {
        try {
            $this->notifier->send(
                new ForgieContactNotification(
                    $this->adminEmail,
                    $summary,
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $verbatim,
                    $channels,
                    $attachment['bytes'] ?? null,
                    $attachment['mime'] ?? null,
                    $attachment['name'] ?? null,
                ),
                new Recipient($this->adminEmail),
            );

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Forgie failed to send a contact message', ['exception' => $e, 'channels' => $channels]);

            return false;
        }
    }

    /**
     * The image (if any) the visitor attached to the current turn, decoded ready to
     * be attached to the admin email.
     *
     * @return array{bytes: string, mime: string, name: string}|null
     */
    private function attachment(): ?array
    {
        $upload = $this->context->getUpload();
        if (null === $upload) {
            return null;
        }

        $bytes = base64_decode($upload->data, true);
        if (false === $bytes) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => $upload->mimeType, 'name' => $upload->filename];
    }

    private function looksLikePlaceholder(string $firstName, string $lastName, string $email): bool
    {
        $names = strtolower($firstName.' '.$lastName);
        foreach (['inconnu', 'unknown', 'n/a', 'nom', 'prenom', 'prénom'] as $token) {
            if (str_contains($names, $token)) {
                return true;
            }
        }

        $at = strrchr($email, '@');
        $domain = false === $at ? '' : strtolower(substr($at, 1));

        return \in_array($domain, ['example.com', 'exemple.com', 'example.org', 'example.net', 'test.com', 'email.com'], true);
    }

    private function verbatim(): string
    {
        $bag = $this->context->get();
        if (null === $bag) {
            return '';
        }

        // Markdown: bold role label + blank line between turns so both the email
        // (markdown_to_html) and Google Chat render the transcript cleanly.
        $turns = [];
        foreach ($bag->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                $turns[] = '**Visiteur :** '.($message->asText() ?? '');

                continue;
            }

            if ($message instanceof AssistantMessage) {
                $text = $this->assistantText($message);
                if ('' !== $text) {
                    $turns[] = '**Forgie :** '.$text;
                }
            }
        }

        return implode("\n\n", $turns);
    }

    private function assistantText(AssistantMessage $message): string
    {
        $text = '';
        foreach ($message->getContent() as $part) {
            if ($part instanceof Text) {
                $text .= $part->getText();
            }
        }

        return $text;
    }
}
