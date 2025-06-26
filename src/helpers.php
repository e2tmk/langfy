<?php

declare(strict_types = 1);

use Langfy\Enums\Context;
use Langfy\Langfy;

if (! function_exists('langfy')) {
    /**
     * Create a new Langfy instance for the specified context.
     */
    function langfy(Context $context, ?string $moduleName = null): Langfy
    {
        return Langfy::for($context, $moduleName);
    }
}
