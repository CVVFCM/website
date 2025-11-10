<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\FacebookLongLivedToken;
use App\DTO\FacebookPageInfo;
use App\DTO\FacebookPageToken;
use App\DTO\FacebookPost;
use App\DTO\InstagramMedia;
use App\Repository\FacebookTokenRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FacebookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private FacebookTokenRepository $facebookTokenRepository,
        private string $facebookAppId,
        private string $facebookAppSecret,
        private string $facebookPageId,
    ) {
    }

    public function getLongLivedToken(string $fbExchangeToken): FacebookLongLivedToken
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf(
                'https://graph.facebook.com/v17.0/oauth/access_token?grant_type=fb_exchange_token&client_id=%s&client_secret=%s&fb_exchange_token=%s',
                $this->facebookAppId,
                $this->facebookAppSecret,
                $fbExchangeToken,
            )
        );

        /**
         * @var object{
         *      access_token: string,
         *      token_type: string,
         *      expires_in?: int,
         *  } $rawContent
         */
        $rawContent = json_decode($response->getContent());

        return new FacebookLongLivedToken($rawContent->access_token, $rawContent->token_type, $rawContent->expires_in ?? 0);
    }

    public function getPageToken(FacebookLongLivedToken $longLivedToken): FacebookPageToken
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf(
                'https://graph.facebook.com/v17.0/%s?fields=instagram_business_account,name,access_token&access_token=%s',
                $this->facebookPageId,
                $longLivedToken->accessToken,
            )
        );

        /**
         * @var object{
         *      name: string,
         *      access_token: string,
         *      instagram_business_account: object{id: string},
         * } $rawContent
         */
        $rawContent = json_decode($response->getContent());

        return new FacebookPageToken(
            $rawContent->name,
            $rawContent->access_token,
            $rawContent->instagram_business_account->id,
        );
    }

    public function getPageFeed(int $limit): array
    {
        $token = $this->facebookTokenRepository->getLast();

        $response = $this->httpClient->request(
            'GET',
            sprintf(
                'https://graph.facebook.com/v17.0/me/feed?fields=id,permalink_url,created_time,full_picture,message,message_tags&limit=%s&access_token=%s',
                $limit * 3,
                $token->pageToken,
            )
        );

        /**
         * @var object{
         *      data: array<int, object{
         *          id: string,
         *          permalink_url: string,
         *          full_picture?: string,
         *          message?: string,
         *          created_time: string,
         *          message_tags?: array<int, object{name: string}>,
         *      }>
         * } $rawContent
         */
        $rawContent = json_decode($response->getContent());

        return array_map(
            fn (object $postData) => new FacebookPost(
                $postData->id,
                $postData->permalink_url,
                $postData->full_picture ?? null,
                $postData->message ?? '',
                new \DateTimeImmutable($postData->created_time),
                array_map(
                    fn (object $messageTag) => $messageTag->name,
                    $postData->message_tags ?? [],
                )
            ),
            array_slice(
                array_filter(
                    $rawContent->data,
                    fn (object $postData) => isset($postData->message),
                ),
                0,
                $limit,
            ),
        );
    }

    public function getPageInfo(): FacebookPageInfo
    {
        $token = $this->facebookTokenRepository->getLast();

        $response = $this->httpClient->request(
            'GET',
            sprintf(
                'https://graph.facebook.com/v17.0/me?fields=name,link,picture.type(large)&access_token=%s',
                $token->pageToken,
            )
        );

        /**
         * @var object{
         *      id: string,
         *      name: string,
         *      picture: object{data: object{url: string}},
         *      link: string
         * } $rawContent
         */
        $rawContent = json_decode($response->getContent());

        return new FacebookPageInfo(
            $rawContent->id,
            $rawContent->name,
            $rawContent->picture->data->url,
            $rawContent->link,
        );
    }

    public function getInstagramMedia(): array
    {
        $token = $this->facebookTokenRepository->getLast();

        $response = $this->httpClient->request(
            'GET',
            sprintf(
                'https://graph.facebook.com/v17.0/%s/media?fields=caption,permalink,thumbnail_url,media_url,media_type&access_token=%s',
                $token->instagramId,
                $token->pageToken,
            )
        );

        /**
         * @var object{
         *      data: array<int, object{
         *          caption?: string,
         *          permalink: string,
         *          thumbnail_url:  string,
         *          media_url?: string,
         *          media_type: string,
         *          id: string
         *      }>
         * } $rawContent
         */
        $rawContent = json_decode($response->getContent());

        return array_map(
            fn (object $media) => new InstagramMedia(
                $media->id,
                $media->media_type,
                $media->caption ?? '',
                $media->permalink,
                $media->media_url ?? $media->thumbnail_url,
            ),
            $rawContent->data,
        );
    }
}
