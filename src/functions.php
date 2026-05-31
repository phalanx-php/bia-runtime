<?php

declare(strict_types=1);

use Phalanx\Dory\Runtime\ScriptContext;
use Phalanx\Dory\Runtime\ScriptContextHolder;

function dory(): ScriptContext
{
    return ScriptContextHolder::current();
}

function dump(mixed ...$values): void
{
    foreach ($values as $value) {
        dory()->dump($value);
    }
}

function dd(mixed ...$values): never
{
    foreach ($values as $value) {
        dory()->dump($value);
    }

    throw new \Phalanx\Cancellation\Halted('dd');
}
