<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\ApiResource\ForgieMessage;
use App\Message\AskForgie;
use App\State\ForgieMessageProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ForgieMessageProcessorTest extends TestCase
{
    private const string UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d251';

    public function testItDispatchesTheQuestion(): void
    {
        $dispatched = [];
        $processor = new ForgieMessageProcessor($this->createBus($dispatched), $this->createLimiter(10), $this->createRequestStack());

        $processor->process($this->createData(), new Post());

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(AskForgie::class, $dispatched[0]);
        $this->assertSame(self::UUID, $dispatched[0]->conversationId);
        $this->assertSame('Bonjour !', $dispatched[0]->message);
    }

    public function testItRejectsWhenTheRateLimitIsExhausted(): void
    {
        $dispatched = [];
        $limiter = $this->createLimiter(1);
        $processor = new ForgieMessageProcessor($this->createBus($dispatched), $limiter, $this->createRequestStack());

        $processor->process($this->createData(), new Post());

        $this->expectException(TooManyRequestsHttpException::class);

        try {
            $processor->process($this->createData(), new Post());
        } finally {
            $this->assertCount(1, $dispatched);
        }
    }

    private function createData(): ForgieMessage
    {
        $data = new ForgieMessage();
        $data->conversationId = self::UUID;
        $data->message = 'Bonjour !';

        return $data;
    }

    /**
     * @param list<object> $dispatched
     */
    private function createBus(array &$dispatched): MessageBusInterface
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        return $bus;
    }

    private function createLimiter(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'forgie_api', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );
    }

    private function createRequestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/forgie/messages', 'POST'));

        return $requestStack;
    }
}
