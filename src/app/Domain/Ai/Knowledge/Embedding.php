<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

use InvalidArgumentException;

final readonly class Embedding
{
    /** @var float[] */
    public array $values;

    /** @param iterable<int, float|int> $values */
    public function __construct(iterable $values)
    {
        $normalized = [];

        foreach ($values as $v) {
            if (!is_numeric($v)) {
                throw new InvalidArgumentException('Embedding components must be numeric');
            }
            $normalized[] = (float) $v;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Embedding must not be empty');
        }

        $this->values = $normalized;
    }

    public function dimension(): int
    {
        return count($this->values);
    }

    /** L2-normalized copy. */
    public function normalized(): self
    {
        $norm = sqrt(array_sum(array_map(static fn (float $v) => $v * $v, $this->values)));

        if ($norm === 0.0) {
            return $this;
        }

        return new self(array_map(static fn (float $v) => $v / $norm, $this->values));
    }
}
