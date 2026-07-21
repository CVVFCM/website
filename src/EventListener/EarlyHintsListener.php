<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Emits an HTTP 103 Early Hints response for the render-critical front-end assets before Sulu renders
 * the page, so the browser can fetch the render-blocking CSS + font during server think-time.
 *
 * The preload list is derived from the `app` importmap entrypoint (never hardcoded, so it tracks the
 * importmap), plus the self-hosted font that {@see templates/base.html.twig} preloads (it is not an
 * importmap entry). {@see Response::sendHeaders()} is a no-op unless the FrankenPHP-only `headers_send`
 * function exists, so this is safe under CLI/tests and only emits a real 103 on FrankenPHP.
 *
 * JS modules are intentionally excluded: a `modulepreload` in the 103 starts module loading before the
 * HTML's `<script type="importmap">` is parsed, and Firefox then rejects the entire import map
 * ("Import maps are not allowed after a module load or preload has started"), leaving every bare/logical
 * specifier unmapped so the assets 404 as text/html. Chrome tolerates it; Firefox does not. The in-head
 * `<link rel="modulepreload">` that importmap() emits still preloads the modules safely (it comes after
 * the map), and `type=module` scripts are deferred anyway, so the 103 loses little by omitting them.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
final readonly class EarlyHintsListener
{
    public function __construct(
        #[Autowire(service: 'asset_mapper.importmap.generator')]
        private ImportMapGenerator $importMapGenerator,
        #[Autowire(service: 'web_link.http_header_serializer')]
        private HttpHeaderSerializer $serializer,
        private AssetMapperInterface $assetMapper,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('GET' !== $request->getMethod()) {
            return;
        }
        if (!str_contains((string) $request->headers->get('Accept'), 'text/html')) {
            return;
        }
        // Front-office only: the website importmap assets are irrelevant to the Sulu admin.
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        try {
            $links = $this->buildLinks();
        } catch (\Throwable) {
            // Early Hints are best-effort — never let them break a request.
            return;
        }

        if ([] === $links) {
            return;
        }

        $response = new Response();
        $response->headers->set('Link', (string) $this->serializer->serialize($links));
        $response->sendHeaders(103);
    }

    /**
     * @return list<Link>
     *
     * @psalm-suppress InternalMethod ImportMapGenerator::getImportMapData is the same call
     *                 ImportMapRenderer makes to build the preload set; there is no public alternative.
     */
    private function buildLinks(): array
    {
        $links = [];

        // Mirror ImportMapRenderer's classification of the entrypoint's preloaded assets, but skip JS
        // modules: emitting `modulepreload` here (in the 103) breaks Firefox's import map — see the
        // class docblock. Only the render-blocking CSS (and the JSON loader assets) are hinted.
        foreach ($this->importMapGenerator->getImportMapData(['app']) as $data) {
            if (!($data['preload'] ?? false)) {
                continue;
            }

            $path = $data['path'];
            $link = match ($data['type']) {
                'css' => (new Link('preload', $path))->withAttribute('as', 'style'),
                'json' => (new Link('preload', $path))->withAttribute('as', 'fetch'),
                default => null,
            };
            if (null !== $link) {
                $links[] = $link;
            }
        }

        // The self-hosted latin font subset is referenced from CSS (not the importmap), so add it
        // explicitly — same resource base.html.twig preloads.
        $fontPath = $this->assetMapper->getPublicPath('fonts/dm-sans-latin.woff2');
        if (null !== $fontPath) {
            $links[] = (new Link('preload', $fontPath))
                ->withAttribute('as', 'font')
                ->withAttribute('type', 'font/woff2')
                ->withAttribute('crossorigin', true);
        }

        return $links;
    }
}
