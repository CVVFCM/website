<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route('/forgie', name: 'forgie')]
final readonly class ForgieController
{
    public function __construct(
        private Environment $twig,
        private HubInterface $hub,
        private Discovery $discovery,
        private Authorization $authorization,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->discovery->addLink($request);
        $this->authorization->setCookie($request, ['*']);

        return new Response($this->twig->render('forgie/index.html.twig', [
            'hub_url' => $this->hub->getPublicUrl(),
        ]));
    }
}
