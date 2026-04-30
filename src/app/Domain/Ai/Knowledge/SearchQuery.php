<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

use InvalidArgumentException;

final readonly class SearchQuery
{
    public function __construct(
        public string $value,
        public int    $limit = 5,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Search query must not be empty');
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('Search limit must be positive');
        }
    }
}
