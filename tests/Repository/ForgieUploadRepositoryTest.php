<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ForgieUpload;
use App\Repository\ForgieUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ForgieUploadRepositoryTest extends KernelTestCase
{
    public function testItReturnsTheMostRecentUploadOfTheConversation(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ForgieUploadRepository $repository */
        $repository = static::getContainer()->get(ForgieUploadRepository::class);

        $conversationId = Uuid::v4()->toRfc4122();
        $other = Uuid::v4()->toRfc4122();

        $em->persist(new ForgieUpload(Uuid::v4()->toRfc4122(), $conversationId, 'b64', 'image/png', 'old.png', 10, new \DateTimeImmutable('2026-07-01 10:00:00')));
        $em->persist(new ForgieUpload(Uuid::v4()->toRfc4122(), $conversationId, 'b64', 'image/png', 'recent.png', 10, new \DateTimeImmutable('2026-07-01 12:00:00')));
        $em->persist(new ForgieUpload(Uuid::v4()->toRfc4122(), $other, 'b64', 'image/png', 'other.png', 10, new \DateTimeImmutable('2026-07-01 13:00:00')));
        $em->flush();

        $latest = $repository->findLatestForConversation($conversationId);

        $this->assertNotNull($latest);
        $this->assertSame('recent.png', $latest->filename);
    }

    public function testItReturnsNullWhenTheConversationHasNoUpload(): void
    {
        self::bootKernel();
        /** @var ForgieUploadRepository $repository */
        $repository = static::getContainer()->get(ForgieUploadRepository::class);

        $this->assertNull($repository->findLatestForConversation(Uuid::v4()->toRfc4122()));
    }
}
