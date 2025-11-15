<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\DTO\FacebookPageInfo;
use App\Service\FacebookService;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FacebookExtension extends AbstractExtension
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly FacebookService $facebookService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('facebook_page_feed', [$this, 'getPageFeed']),
            new TwigFunction('facebook_page_info', [$this, 'getPageInfo']),
            new TwigFunction('instagram_feed', [$this, 'getInstagramFeed']),
        ];
    }

    public function getPageFeed(int $limit): array
    {
        try {
            return $this->cache->get(
                'facebook_page_feed_'.$limit,
                function (CacheItemInterface $cacheItem) use ($limit) {
                    $cacheItem->expiresAfter(60 * 60);

                    return $this->facebookService->getPageFeed($limit);
                },
            );
        } catch (\Exception $exception) {
            $this->logger->error('Error fetching Facebook page feed: '.$exception->getMessage());

            return [];
        }
    }

    public function getPageInfo(): ?FacebookPageInfo
    {
        try {
            return $this->cache->get(
                'facebook_page_info',
                function (CacheItemInterface $cacheItem) {
                    $cacheItem->expiresAfter(60 * 60 * 8);

                    return $this->facebookService->getPageInfo();
                },
            );
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Facebook page info: '.$e->getMessage());

            return null;
        }
    }

    public function getInstagramFeed(): array
    {
        try {
            return $this->cache->get(
                'instagram_feed',
                function (CacheItemInterface $cacheItem) {
                    $cacheItem->expiresAfter(60 * 60);

                    try {
                        $instagramMedia = $this->facebookService->getInstagramMedia();
                    } catch (ClientException $exception) {
                        return [];
                    }

                    return $instagramMedia;
                },
            );
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Instagram feed: '.$e->getMessage());

            return [];
        }
    }
}
