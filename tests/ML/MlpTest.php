<?php

declare(strict_types=1);

namespace App\Tests\ML;

use App\ML\Mlp;
use PHPUnit\Framework\TestCase;

final class MlpTest extends TestCase
{
    public function testForwardComputesDenseLayersWithRelu(): void
    {
        // Layer 1 (ReLU): neurons pick x0, x1, x0+x1 → pre-activation [2, -3, -1] → ReLU [2, 0, 0].
        // Output layer (no ReLU): sum + bias → 2 + 0 + 0 + 0.5 = 2.5.
        $mlp = new Mlp([
            ['weight' => [[1.0, 0.0], [0.0, 1.0], [1.0, 1.0]], 'bias' => [0.0, 0.0, 0.0], 'relu' => true],
            ['weight' => [[1.0, 1.0, 1.0]], 'bias' => [0.5], 'relu' => false],
        ]);

        $this->assertEqualsWithDelta([2.5], $mlp->forward([2.0, -3.0]), 1e-9);
    }

    public function testReluClampsNegativesToZero(): void
    {
        $mlp = new Mlp([
            ['weight' => [[1.0]], 'bias' => [0.0], 'relu' => true],
        ]);

        $this->assertSame([0.0], $mlp->forward([-5.0]));
        $this->assertSame([5.0], $mlp->forward([5.0]));
    }
}
