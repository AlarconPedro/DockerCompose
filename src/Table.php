<?php

declare(strict_types=1);

namespace Alfa;

use Attribute;

#[Attribute]
class Table
{
    public function __construct(
        public string $name
    )
    {}
}