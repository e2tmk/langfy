<?php

declare(strict_types = 1);

namespace Langfy\FinderPatterns;

abstract class Pattern
{
    abstract public function getPatterns(): array;
}
