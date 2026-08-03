<?php

declare(strict_types=1);

namespace App\Controller;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Category pages are structural only: any website request to their URL returns 404.
 * The Sulu admin preview ($preview = true) still renders them so editors can see the content.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @extends ContentController<DimensionContentInterface>
 */
final class CategoryController extends ContentController
{
    #[\Override]
    public function indexAction(Request $request, DimensionContentInterface $object, string $view, bool $preview = false, bool $partial = false): Response
    {
        if (!$preview) {
            throw $this->createNotFoundException('Category pages are not directly accessible.');
        }

        return parent::indexAction($request, $object, $view, $preview, $partial);
    }
}
