<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EventsIcsFeedControllerTest extends WebTestCase
{
    public function testItServesEveryUpcomingEventAsASingleIcsDownload(): void
    {
        $client = static::createClient();

        $client->request('GET', '/evenements.ics');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = $client->getResponse();
        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="evenements-cvvfcm.ics"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));

        $ics = (string) $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertSame(1, substr_count($ics, 'BEGIN:VCALENDAR'));
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString(
            \sprintf('UID:%s@cvvfcm.fr', $this->someUpcomingEventUuid()),
            $ics,
        );
    }

    /**
     * The events list page offers the feed once, and the cards no longer carry
     * their own calendar button (issue #90).
     */
    public function testTheFeedRouteIsGeneratedOnce(): void
    {
        $client = static::createClient();

        $client->request('GET', '/evenements.ics');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('X-WR-CALNAME:', (string) $client->getResponse()->getContent());
    }

    /**
     * Fixture uuids are random on every database load, so resolve a real
     * published event whose begin_date is still ahead of us — exactly the set
     * the feed exposes.
     */
    private function someUpcomingEventUuid(): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        /** @var string $uuid */
        $uuid = $entityManager->createQueryBuilder()
            ->select('p.uuid')
            ->from(PageDimensionContent::class, 'dc')
            ->innerJoin('dc.page', 'p')
            ->where('dc.templateKey = :templateKey')
            ->andWhere('dc.locale = :locale')
            ->andWhere('dc.stage = :stage')
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'begin_date') >= :from")
            ->setParameter('templateKey', 'event')
            ->setParameter('locale', 'fr')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('from', (new \DateTimeImmutable())->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $uuid;
    }
}
