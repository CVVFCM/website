<?php

declare(strict_types=1);

namespace App\Form;

use Sulu\Bundle\FormBundle\Form\Builder;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Workaround for an upstream sulu/form-bundle bug: `Sulu\Bundle\FormBundle\Form\Builder` caches the
 * forms it builds in an instance property, but the `sulu_form.builder` service is not tagged
 * `kernel.reset` and the class exposes no `reset()`. In FrankenPHP worker mode the service outlives
 * the request, so a form cached while rendering a GET is handed back to the next POST served by the
 * same worker: that form is not submitted, and the request listener drops the submission silently.
 * The parent cache is private, hence the reimplemented `build()`. Both this class and its compiler
 * pass can be dropped once the bundle tags its own builder as resettable.
 *
 * The intra-request cache is kept on purpose: the request listener handles the POST before the page
 * is rendered, and rebuilding the form for the rendering would lose the validation errors of an
 * invalid submission.
 */
final class ResettableFormBuilder extends Builder implements ResetInterface
{
    /** @var array<string, FormInterface|null> */
    private array $cache = [];

    /**
     * The bundle annotates buildForm() as returning FormInterface<mixed>, but the interface
     * takes no template parameter.
     *
     * @psalm-suppress TooManyTemplateParams
     */
    #[\Override]
    public function build(int $id, string $type, string $typeId, ?string $locale = null, string $name = 'form'): ?FormInterface
    {
        if (null === $locale || '' === $locale) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        }

        if (null === $locale || '' === $locale) {
            return null;
        }

        $key = \implode('__', [(string) $id, $type, $typeId, $locale, $name]);

        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->buildForm($id, $type, $typeId, $locale, $name);
        }

        return $this->cache[$key];
    }

    #[\Override]
    public function reset(): void
    {
        $this->cache = [];
    }
}
