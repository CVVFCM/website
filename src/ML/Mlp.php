<?php

declare(strict_types=1);

namespace App\ML;

/**
 * Minimal feed-forward network: a stack of dense layers (`out = bias + input · Wᵀ`) with an optional
 * ReLU per layer. Pure computation, no dependency — enough to run the small weather-correction MLP
 * exported from PyTorch without an ONNX runtime.
 */
final readonly class Mlp
{
    /**
     * @param list<array{weight: list<list<float>>, bias: list<float>, relu: bool}> $layers
     *
     * Each layer: `weight` is [out][in] (PyTorch nn.Linear layout), `bias` is [out].
     */
    public function __construct(private array $layers)
    {
    }

    /**
     * @param list<float> $input
     *
     * @return list<float>
     */
    public function forward(array $input): array
    {
        $x = $input;

        foreach ($this->layers as $layer) {
            $out = [];
            foreach ($layer['weight'] as $neuron => $weights) {
                $sum = $layer['bias'][$neuron];
                foreach ($weights as $i => $weight) {
                    $sum += $x[$i] * $weight;
                }
                $out[] = $layer['relu'] ? max(0.0, $sum) : $sum;
            }
            $x = $out;
        }

        return $x;
    }
}
