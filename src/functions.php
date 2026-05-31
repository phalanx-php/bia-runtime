<?php

declare(strict_types=1);

use Phalanx\Cancellation\Halted;
use Phalanx\Dory\Runtime\ScriptContext;
use Phalanx\Dory\Runtime\ScriptContextHolder;

if (!function_exists('dory')) {
    function dory(): ScriptContext
    {
        return ScriptContextHolder::current();
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        foreach ($values as $value) {
            dory()->dump($value);
        }
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        foreach ($values as $value) {
            dory()->dump($value);
        }

        throw new Halted('dd');
    }
}
