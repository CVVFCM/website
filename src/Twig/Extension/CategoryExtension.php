<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Repository\CategoryPageRepository;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CategoryExtension extends AbstractExtension
{
    /** @var array<string, string|null> */
    private array $firstPageSlugs = [];

    public function __construct(
        private readonly CategoryPageRepository $categoryPageRepository,
        private readonly RequestAnalyzerInterface $requestAnalyzer,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_first_page_url', $this->getFirstPageUrl(...)),
        ];
    }

    /**
     * Route slug of the first page inside the category, to feed to sulu_content_path().
     * Null when the category has no published page (no link should be rendered then).
     */
    public function getFirstPageUrl(?string $categoryUuid): ?string
    {
        if (null === $categoryUuid || '' === $categoryUuid) {
            return null;
        }

        $locale = $this->requestAnalyzer->getCurrentLocalization()?->getLocale() ?? 'fr';
        $key = $categoryUuid.'|'.$locale;
        if (!\array_key_exists($key, $this->firstPageSlugs)) {
            $this->firstPageSlugs[$key] = $this->categoryPageRepository->findFirstPageSlug($categoryUuid, $locale);
        }

        return $this->firstPageSlugs[$key];
    }
}
