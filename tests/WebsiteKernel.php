<?php

namespace App\Tests;

use App\Kernel;

final class WebsiteKernel extends Kernel
{
    public function __construct($environment, $debug)
    {
        parent::__construct($environment, $debug, self::CONTEXT_WEBSITE);
    }
}
