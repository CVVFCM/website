<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Form\ResettableFormBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes the Sulu form builder resettable between requests, see {@see ResettableFormBuilder}.
 */
final class ResettableFormBuilderPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        // The form bundle is not installed in every context, and it could rename the service id.
        if (!$container->hasDefinition('sulu_form.builder')) {
            return;
        }

        // Swapping the class instead of redeclaring the service keeps the eight upstream
        // constructor arguments in sync with the bundle.
        $container->getDefinition('sulu_form.builder')
            ->setClass(ResettableFormBuilder::class)
            ->addTag('kernel.reset', ['method' => 'reset']);
    }
}
