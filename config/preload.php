<?php

declare(strict_types=1);

// DualKernel runs two Sulu kernels (admin + website), each with its own cache dir — so there is no
// single var/cache/prod preload file. Require whichever context caches exist; file_exists() keeps a
// not-yet-warmed context from breaking the whole preload (a missing preload file wipes PHP's built-in
if (file_exists(dirname(__DIR__).'/var/cache/admin/prod/App_KernelProdContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/admin/prod/App_KernelProdContainer.preload.php';
}

if (file_exists(dirname(__DIR__).'/var/cache/website/prod/App_KernelProdContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/website/prod/App_KernelProdContainer.preload.php';
}
