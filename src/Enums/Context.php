<?php

declare(strict_types = 1);

namespace Langfy\Enums;

enum Context: string
{
    case Application = 'application';
    case Module      = 'module';
}
