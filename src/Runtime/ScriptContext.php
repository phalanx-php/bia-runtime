<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Closure;
use Phalanx\Dory\Orchestration\AttemptBuilder;
use Phalanx\Dory\Scoped\ScopedFiles;
use Phalanx\Dory\Scoped\ScopedHttpClient;
use Phalanx\Scope\ExecutionScope;

interface ScriptContext extends ExecutionScope
{
    public string $scriptPath { get; }

    public string $scriptName { get; }

    public DoryConfig $config { get; }

    public ScopedHttpClient $http { get; }

    public ScopedFiles $fs { get; }

    public function attempt(Closure $task): AttemptBuilder;

    public function dump(mixed ...$values): void;

    public function println(string $message = ''): void;
}
