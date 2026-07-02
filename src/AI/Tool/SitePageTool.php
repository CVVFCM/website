<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\PageContentRepository;
use App\AI\TemplateData;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * Site navigation for Forgie: list the published (non-event) pages, then fetch the
 * full text content of one page by its url.
 */
#[AsTool('site_pages', 'Liste toutes les pages publiées du site du club (hors événements) : titre, url et courte description. Utiliser page_content pour lire une page en entier.', method: 'list')]
#[AsTool('page_content', 'Donne le contenu texte complet d\'une page du site à partir de son url (obtenue via site_pages).', method: 'content')]
final readonly class SitePageTool
{
    public function __construct(
        private PageContentRepository $pageContentRepository,
    ) {
    }

    /**
     * @return array{pages: list<array{titre: string, url: string|null, description: string|null}>}
     */
    public function list(): array
    {
        $pages = [];
        foreach ($this->pageContentRepository->findPages(['event']) as $page) {
            $url = $page['url'] ?? null;
            $title = $page['title'] ?? null;

            // Pages without a route or title are not navigable — useless to the assistant.
            if (!\is_string($url) || !\is_string($title) || '' === $title) {
                continue;
            }

            $description = TemplateData::plainText($page['description'] ?? null);

            $pages[] = [
                'titre' => $title,
                'url' => $url,
                'description' => null !== $description && mb_strlen($description) > 200
                    ? mb_substr($description, 0, 200).'…'
                    : $description,
            ];
        }

        return ['pages' => $pages];
    }

    /**
     * @return array{erreur: string}|array{titre: string, url: string, contenu: string}
     */
    public function content(
        #[Schema(description: 'Url de la page, par exemple "/nous-rejoindre" (voir site_pages).')]
        string $url,
    ): array {
        $page = $this->pageContentRepository->findPageByUrl($url);

        if (null === $page) {
            return ['erreur' => 'Page introuvable.'];
        }

        $texts = [];
        $this->collectText($page, $texts);

        return [
            'titre' => (string) ($page['title'] ?? ''),
            'url' => $url,
            'contenu' => implode("\n", $texts),
        ];
    }

    /**
     * Flattens every meaningful string of a templateData tree to plain text.
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $texts
     */
    private function collectText(array $data, array &$texts): void
    {
        /** @var mixed $value */
        foreach ($data as $key => $value) {
            if (\is_string($key) && \in_array($key, ['url', 'template', 'locale', 'id', 'uuid', 'ids', 'dataSource'], true)) {
                continue;
            }

            if (\is_array($value)) {
                $this->collectText($value, $texts);

                continue;
            }

            $text = TemplateData::plainText($value);
            if (null !== $text && !\in_array($text, $texts, true)) {
                $texts[] = $text;
            }
        }
    }
}
