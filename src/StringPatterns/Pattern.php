<?php

declare(strict_types = 1);

namespace Langfy\StringPatterns;

abstract class Pattern
{
    abstract public function getPatterns(): array;
}
