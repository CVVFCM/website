<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class EventIcsControllerTest extends WebTestCase
{
    public function testItServesAPublishedEventAsAnIcsDownload(): void
    {
        $client = static::createClient();
        $uuid = $this->somePublishedEventUuid();

        $client->request('GET', \sprintf('/evenements/%s.ics', $uuid));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = $client->getResponse();
        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.ics', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));

        $ics = (string) $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString(\sprintf('UID:%s@cvvfcm.fr', $uuid), $ics);
        $this->assertStringContainsString('SUMMARY:', $ics);
        $this->assertStringContainsString('DTSTART', $ics);
    }

    public function testARedownloadKeepsTheSameUidSoCalendarsUpdateInsteadOfDuplicating(): void
    {
        $client = static::createClient();
        $uuid = $this->somePublishedEventUuid();
        $expectedUid = \sprintf('UID:%s@cvvfcm.fr', $uuid);

        $client->request('GET', \sprintf('/evenements/%s.ics', $uuid));
        $this->assertStringContainsString($expectedUid, (string) $client->getResponse()->getContent());

        $client->request('GET', \sprintf('/evenements/%s.ics', $uuid));
        $this->assertStringContainsString($expectedUid, (string) $client->getResponse()->getContent());
    }

    public function testAnUnknownUuidIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', \sprintf('/evenements/%s.ics', Uuid::v7()->toRfc4122()));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Fixture uuids are random on every database load, so resolve a real
     * published event uuid at runtime.
     */
    private function somePublishedEventUuid(): string
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
            ->setParameter('templateKey', 'event')
            ->setParameter('locale', 'fr')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $uuid;
    }
}
