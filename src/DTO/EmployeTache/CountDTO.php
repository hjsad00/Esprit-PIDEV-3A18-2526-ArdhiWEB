<?php

namespace App\DTO\EmployeTache;

class CountDTO
{
    public function __construct(
        public readonly mixed $key,
        public readonly int $total
    ) {}
}
