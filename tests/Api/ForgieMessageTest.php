<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Message\AskForgie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ForgieMessageTest extends WebTestCase
{
    private const string UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d251';

    public function testItAcceptsAMessageAndQueuesIt(): void
    {
        $client = static::createClient();
        $this->postJsonLd($client, [
            'conversationId' => self::UUID,
            'message' => 'Bonjour Forgie !',
        ]);

        $this->assertResponseStatusCodeSame(202);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(1, $transport->getSent());

        $message = $transport->getSent()[0]->getMessage();
        $this->assertInstanceOf(AskForgie::class, $message);
        $this->assertSame(self::UUID, $message->conversationId);
        $this->assertSame('Bonjour Forgie !', $message->message);
    }

    public function testItRejectsABlankMessage(): void
    {
        $client = static::createClient();
        $this->postJsonLd($client, [
            'conversationId' => self::UUID,
            'message' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->getSent());
    }

    public function testItRejectsAnInvalidConversationId(): void
    {
        $client = static::createClient();
        $this->postJsonLd($client, [
            'conversationId' => 'not-a-uuid',
            'message' => 'Bonjour !',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * @param array{conversationId: string, message: string} $payload
     */
    private function postJsonLd(KernelBrowser $client, array $payload): void
    {
        $client->request(
            'POST',
            '/api/forgie/messages',
            server: ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
