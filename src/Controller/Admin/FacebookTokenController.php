<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FacebookToken;
use App\Repository\FacebookTokenRepository;
use App\Service\FacebookService;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\FieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @RouteResource("facebook_token")
 */
#[AsController]
#[Route(path: '/admin/api')]
final readonly class FacebookTokenController
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
        private FacebookService $facebookService,
        private FacebookTokenRepository $facebookTokenRepository,
    ) {
    }

    #[Route('/facebook-tokens', name: 'app.get_facebook_tokens', methods: ['GET'])]
    public function cgetAction(): Response
    {
        /**
         * @var array{
         *      createdAt: FieldDescriptorInterface,
         * } $fieldDescriptors
         */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(FacebookToken::RESOURCE_KEY);
        $listBuilder = $this->listBuilderFactory
            ->create(FacebookToken::class)
            ->sort($fieldDescriptors['createdAt'], 'desc');
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            FacebookToken::RESOURCE_KEY,
            $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count()
        );

        return new JsonResponse($this->normalizer->normalize(
            $listRepresentation->toArray(),
            'json',
            ['sulu_admin' => true, 'sulu_admin_custom_url' => true, 'sulu_admin_custom_url_list' => true],
        ));
    }

    #[Route('/facebook-tokens/requests/longs-liveds', methods: ['POST'])]
    public function postRequestLongLivedAction(Request $request): JsonResponse
    {
        $accessToken = $request->request->get('accessToken');
        if (!\is_string($accessToken)) {
            return new JsonResponse(['message' => 'Invalid access token'], Response::HTTP_BAD_REQUEST);
        }

        $longLivedToken = $this->facebookService->getLongLivedToken($accessToken);
        $pageToken = $this->facebookService->getPageToken($longLivedToken);

        $this->facebookTokenRepository->save(
            new FacebookToken(
                $longLivedToken->accessToken,
                new \DateTimeImmutable(sprintf('+%d seconds', $longLivedToken->expiresIn)),
                $pageToken->accessToken,
                $pageToken->pageName,
                $pageToken->instagramId,
            )
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
