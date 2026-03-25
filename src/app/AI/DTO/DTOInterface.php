<?php

namespace App\AI\DTO;

interface DTOInterface
{
    public static function fromArray(array $data): self;

    public function toArray(): array;
}
