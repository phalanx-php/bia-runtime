<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

interface CodeParser
{
    public function parseSource(string $source, ?string $name = null): ParseResult;

    public function parseFile(string $path): ParseResult;
}
