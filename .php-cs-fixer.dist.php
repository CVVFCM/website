<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('config/secrets')
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
