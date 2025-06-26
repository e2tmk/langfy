<?php

declare(strict_types = 1);

pest()->extend(Langfy\Tests\TestCase::class)
    ->in('Features');

function tests_cache_path(): string
{
    return __DIR__ . '/cache';
}
